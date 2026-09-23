<?php
/**
 * This script is used to process incoming TLS and DMARC reports sent to the postmaster email address.
 * It connects to the IMAP server, retrieves the reports, decodes them, and stores the relevant information in the database.
 */
require_once __DIR__ . '/../common_config.php';

class Mail_Report_Processor
{
	private PDOStatement $insert_tls_report;
	private PDOStatement $insert_tls_policy;
	private PDOStatement $insert_tls_policy_failure;
	private PDOStatement $insert_dmarc_report;
	private PDOStatement $insert_dmarc_report_error;
	private PDOStatement $insert_dmarc_report_record;
	private PDOStatement $insert_dmarc_report_record_reason;
	private PDOStatement $insert_dmarc_report_record_dkim_result;
	private PDOStatement $insert_dmarc_report_record_spf_result;
	private PDO $db;

	public function __construct()
	{
		$this->db = get_db_instance();
		$this->insert_tls_report = $this->db->prepare('INSERT INTO tls_report (report_id, organization, contact, start, end) VALUES (?, ?, ?, ?, ?);');
		$this->insert_tls_policy = $this->db->prepare('INSERT INTO tls_report_policy (report_id, policy_type, policy_string, policy_domain, mx_host, success, failure) VALUES (?, ?, ?, ?, ?, ?, ?);');
		$this->insert_tls_policy_failure = $this->db->prepare('INSERT INTO tls_report_policy_failure (policy_id, result_type, sender_ip, receiver_ip, receiver_hostname, receiver_helo, session_count, additional_information, reason_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$this->insert_dmarc_report = $this->db->prepare('INSERT INTO dmarc_report (report_id, email, org_name, begin, end, domain, adkim, aspf, policy, subdomain_policy, pct) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$this->insert_dmarc_report_error = $this->db->prepare('INSERT INTO dmarc_report_errors (report_id, error) VALUES (?, ?);');
		$this->insert_dmarc_report_record = $this->db->prepare('INSERT INTO dmarc_report_records (report_id, source_ip, count, policy_evaluated_disposition, policy_evaluated_dkim, policy_evaluated_spf, identifier_envelope_to, identifier_envelope_from, identifier_header_from) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$this->insert_dmarc_report_record_reason = $this->db->prepare('INSERT INTO dmarc_report_record_reason (record_id, type, comment) VALUES (?, ?,?);');
		$this->insert_dmarc_report_record_dkim_result = $this->db->prepare('INSERT INTO dmarc_report_record_dkim_result (record_id, domain, selector, result, human_result) VALUES (?, ?, ?, ?, ?);');
		$this->insert_dmarc_report_record_spf_result = $this->db->prepare('INSERT INTO dmarc_report_record_spf_result (record_id, domain, result, scope) VALUES (?, ?, ?, ?);');
	}

	public function run() : void
	{
		$imap = imap_open("{localhost:993/ssl/novalidate-cert}INBOX", POSTMASTER_EMAIL, POSTMASTER_PASSWORD);
		$messages = imap_sort($imap,  SORTDATE, true);
		foreach($messages as $message){
			$structure = imap_fetchstructure($imap, $message);
			// TLS Report
			if($structure && $structure->subtype === 'REPORT'){
				foreach($structure->parameters as $parameter){
					if($parameter->attribute === 'report-type' && $parameter->value === 'tlsrpt'){
						foreach($structure->parts as $part_number => $part){
							if(!in_array($part->subtype, ['TLSRPT+JSON', 'TLSRPT+GZIP'], true)){
								continue;
							}
							$encoded_body = imap_fetchbody($imap, $message, $part_number + 1);
							if($part->encoding === ENC7BIT){
								$decoded_body = quoted_printable_decode($encoded_body);
							}elseif($part->encoding === ENCBASE64){
								$decoded_body = imap_base64($encoded_body);
							}elseif($part->encoding === ENCQUOTEDPRINTABLE ){
								$decoded_body = quoted_printable_decode($encoded_body);
							} else {
								$decoded_body = $encoded_body;
							}
							if($part->subtype === 'TLSRPT+GZIP'){
								$decoded_body = gzdecode($decoded_body);
							}
							$report = json_decode($decoded_body, true);
							if(!empty($report['report-id'])){
								try {
									$this->insert_tls_report->execute([$report['report-id'] ?? '', $report['organization-name'] ?? '', $report['contact-info'] ?? '', date('Y-m-d H:i:s', strtotime($report['date-range']['start-datetime'] ?? 'now')), date('Y-m-d H:i:s', strtotime($report['date-range']['end-datetime'] ?? 'now'))]);
									$report_id = $this->db->lastInsertId();
									foreach($report['policies'] as $policy){
										$this->insert_tls_policy->execute([$report_id, $policy['policy']['policy-type'] ?? '', json_encode($policy['policy']['policy-string'] ?? ''), $policy['policy']['policy-domain'] ?? '', json_encode($policy['policy']['mx-host'] ?? ''), $policy['summary']['total-successful-session-count'], $policy['summary']['total-failure-session-count']]);
										if(!empty($policy['failure-details'])){
											$policy_id = $this->db->lastInsertId();
											foreach($policy['failure-details'] as $failure){
												$this->insert_tls_policy_failure->execute([$policy_id, $failure['result-type'] ?? '', $failure['sending-mta-ip'] ?? '', $failure['receiving-ip'] ?? '', $failure['receiving-mx-hostname'] ?? '', $failure['receiving-mx-helo'] ?? '', $failure['failed-session-count'], $failure['additional-information'] ?? '', $failure['failure-reason-code'] ?? '']);
											}
										}
									}
								} catch(PDOException $e){
									// delete only duplicates
									if($e->getCode() !== '23000'){
										continue;
									}
								}
								imap_mail_move($imap, $message, 'Trash');
							}
							break;
						}
						break;
					}
				}
			// DMARC Report
			} elseif($structure && in_array($structure->subtype, ['MIXED', 'RELATED'], true)){
				foreach($structure->parts as $part_number => $part){
					if(!in_array($part->subtype, ['ZIP', 'GZIP', 'XML', 'OCTET-STREAM'], true)) {
						continue;
					}
					$encoded_body = imap_fetchbody($imap, $message, $part_number + 1);
					if($part->encoding === ENC7BIT){
						$decoded_body = quoted_printable_decode($encoded_body);
					}elseif($part->encoding === ENCBASE64){
						$decoded_body = imap_base64($encoded_body);
					}elseif($part->encoding === ENCQUOTEDPRINTABLE ){
						$decoded_body = quoted_printable_decode($encoded_body);
					} else {
						$decoded_body = $encoded_body;
					}
					$temp = tempnam(sys_get_temp_dir(), 'attachment');
					file_put_contents($temp, $decoded_body);
					if(mime_content_type($temp) === 'application/zip') {
						$zip = new \ZipArchive();
						if($zip->open($temp) !== false && $zip->numFiles === 1) {
							$decoded_body = $zip->getFromIndex(0);
						}
					} elseif(mime_content_type($temp) === 'application/gzip') {
						$decoded_body = gzdecode($decoded_body);
					}
					unlink($temp);
					try {
						if($this->process_dmarc_xml($decoded_body)){
							imap_mail_move($imap, $message, 'Trash');
							break;
						}
					} catch(PDOException $e){
						// delete only duplicates
						if($e->getCode() === '23000'){
							imap_mail_move($imap, $message, 'Trash');
						}
					}
				}
			// DMARC Report
			} elseif($structure && in_array($structure->subtype, ['ZIP', 'GZIP', 'XML'], true)){
				$encoded_body = imap_body($imap, $message);
				if($structure->encoding === ENC7BIT){
					$decoded_body = quoted_printable_decode($encoded_body);
				}elseif($structure->encoding === ENCBASE64){
					$decoded_body = imap_base64($encoded_body);
				}elseif($structure->encoding === ENCQUOTEDPRINTABLE ){
					$decoded_body = quoted_printable_decode($encoded_body);
				} else {
					$decoded_body = $encoded_body;
				}
				$temp = tempnam(sys_get_temp_dir(), 'attachment');
				file_put_contents($temp, $decoded_body);
				if(mime_content_type($temp) === 'application/zip') {
					$zip = new \ZipArchive();
					if($zip->open($temp) !== false && $zip->numFiles === 1) {
						$decoded_body = $zip->getFromIndex(0);
					}
				} elseif(mime_content_type($temp) === 'application/gzip') {
					$decoded_body = gzdecode($decoded_body);
				}
				unlink($temp);
				try {
					if($this->process_dmarc_xml($decoded_body)){
						imap_mail_move($imap, $message, 'Trash');
					}
				} catch(PDOException $e){
					// delete only duplicates
					if($e->getCode() === '23000'){
						imap_mail_move($imap, $message, 'Trash');
					}
				}
			}
		}
		imap_expunge($imap);
		imap_close($imap);
	}

	private function process_dmarc_xml(string $decoded_body) : bool
	{
		$xml = @simplexml_load_string(str_replace('xmlns=', 'ns=', $decoded_body));
		if($xml !== false) {
			$org_name = (string) ($xml->xpath('/feedback/report_metadata/org_name')[0] ?? '');
			$email = (string) ($xml->xpath('/feedback/report_metadata/email')[0] ?? '');
			$report_id = (string) ($xml->xpath('/feedback/report_metadata/report_id')[0] ?? '');
			$begin = (string) ($xml->xpath('/feedback/report_metadata/date_range/begin')[0] ?? '');
			$end = (string) ($xml->xpath('/feedback/report_metadata/date_range/end')[0] ?? '');
			$errors = $xml->xpath('/feedback/report_metadata/error');
			$domain = (string) ($xml->xpath('/feedback/policy_published/domain')[0] ?? '');
			$adkim = (string) ($xml->xpath('/feedback/policy_published/adkim')[0] ?? '');
			$aspf = (string) ($xml->xpath('/feedback/policy_published/aspf')[0] ?? '');
			$policy = (string) ($xml->xpath('/feedback/policy_published/p')[0] ?? '');
			$subdomain_policy = (string) ($xml->xpath('/feedback/policy_published/sp')[0] ?? '');
			$pct = (int) ($xml->xpath('/feedback/policy_published/pct')[0] ?? 100);
			$records = $xml->xpath('/feedback/record');
			if(!empty($org_name) && !empty($report_id)){
				$this->insert_dmarc_report->execute([$report_id, $email, $org_name, date('Y-m-d H:i:s', $begin), date('Y-m-d H:i:s', $end), $domain, $adkim, $aspf, $policy, $subdomain_policy, $pct]);
				$report_id = $this->db->lastInsertId();
				foreach($errors as $error){
					$this->insert_dmarc_report_error->execute([$report_id, (string) $error]);
				}
				foreach($records as $record){
					$source_ip = (string) ($record->xpath('row/source_ip')[0] ?? '');
					$count = (string) ($record->xpath('row/count')[0] ?? '');
					$policy_evaluated_disposition = (string) ($record->xpath('row/policy_evaluated/disposition')[0] ?? '');
					$policy_evaluated_dkim = (string) ($record->xpath('row/policy_evaluated/dkim')[0] ?? '');
					$policy_evaluated_spf = (string) ($record->xpath('row/policy_evaluated/spf')[0] ?? '');
					$policy_evaluated_reasons = $record->xpath('row/policy_evaluated/reason');
					$identifier_envelope_to = (string) ($record->xpath('identifiers/envelope_to')[0] ?? '');
					$identifier_envelope_from = (string) ($record->xpath('identifiers/envelope_from')[0] ?? '');
					$identifier_header_from = (string) ($record->xpath('identifiers/header_from')[0] ?? '');
					$this->insert_dmarc_report_record->execute([$report_id, $source_ip, $count, $policy_evaluated_disposition, $policy_evaluated_dkim, $policy_evaluated_spf, $identifier_envelope_to, $identifier_envelope_from, $identifier_header_from]);
					$record_id = $this->db->lastInsertId();
					foreach($policy_evaluated_reasons as $reason){
						$policy_evaluated_reason_type = (string) ($reason->xpath('type')[0] ?? '');
						$policy_evaluated_reason_comment = (string) ($reason->xpath('comment')[0] ?? '');
						$this->insert_dmarc_report_record_reason->execute([$record_id, $policy_evaluated_reason_type, $policy_evaluated_reason_comment]);
					}
					$auth_results_dkim = $record->xpath('auth_results/dkim');
					foreach($auth_results_dkim as $dkim_result){
						$auth_result_dkim_domain = (string) ($dkim_result->xpath('domain')[0] ?? '');
						$auth_result_dkim_selector = (string) ($dkim_result->xpath('selector')[0] ?? '');
						$auth_result_dkim_result = (string) ($dkim_result->xpath('result')[0] ?? '');
						$auth_result_dkim_human_result = (string) ($dkim_result->xpath('human_result')[0] ?? '');
						$this->insert_dmarc_report_record_dkim_result->execute([$record_id, $auth_result_dkim_domain, $auth_result_dkim_selector, $auth_result_dkim_result, $auth_result_dkim_human_result]);
					}
					$auth_results_spf = $record->xpath('auth_results/spf');
					foreach($auth_results_spf as $spf_result){
						$auth_result_spf_domain = (string) ($spf_result->xpath('domain')[0] ?? '');
						$auth_result_spf_scope = (string) ($spf_result->xpath('scope')[0] ?? '');
						$auth_result_spf_result = (string) ($spf_result->xpath('result')[0] ?? '');
						$this->insert_dmarc_report_record_spf_result->execute([$record_id, $auth_result_spf_domain, $auth_result_spf_result, $auth_result_spf_scope]);
					}
				}
				return true;
			}
		}
		return false;
	}
}
$processor = new Mail_Report_Processor();
$processor->run();
