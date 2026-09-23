<?php
if(!extension_loaded('gettext')){
	die('The gettext extension of PHP is required. Please install it first.' . PHP_EOL);
}
require('common_config.php');
foreach(['pdo_mysql', 'mbstring', 'pcre', 'gnupg', 'intl'] as $required_extension) {
	if ( ! extension_loaded( $required_extension ) ) {
		die( sprintf( _( 'The %s extension of PHP is required. Please install it first.' ), $required_extension ) . PHP_EOL );
	}
}
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
}catch(PDOException){
	try{
		//Attempt to create database
		$db=new PDO('mysql:host=' . DBHOST . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
		if(false!==$db->exec('CREATE DATABASE ' . DBNAME)){
			$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
		}else{
			die( _('No Connection to MySQL database!') . PHP_EOL);
		}
	}catch(PDOException){
		die( _('No Connection to MySQL database!') . PHP_EOL);
	}
}
$createTableStatements = [
	'admin' => "CREATE TABLE IF NOT EXISTS `admin` (`username` varchar(255) NOT NULL, `password` varchar(255) NOT NULL, `superadmin` tinyint(1) NOT NULL DEFAULT 0, `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `modified` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `active` tinyint(1) NOT NULL DEFAULT 1, `password_hash_type` varchar(20) NOT NULL DEFAULT '{MD5-CRYPT}', PRIMARY KEY (`username`), KEY `active` (`active`)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'domain' => "CREATE TABLE IF NOT EXISTS `domain` (`domain` varchar(255) NOT NULL, `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `modified` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `active` tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (`domain`), KEY `active` (`active`)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'alias' => "CREATE TABLE IF NOT EXISTS `alias` (`address` varchar(255) NOT NULL, `goto` text NOT NULL, `domain` varchar(255) NOT NULL, `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `modified` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `active` tinyint(1) NOT NULL DEFAULT 1, `enforce_tls_in` tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (`address`), KEY `domain` (`domain`), KEY `active` (`active`), CONSTRAINT `alias_ibfk_1` FOREIGN KEY (`domain`) REFERENCES `domain` (`domain`) ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'alias_domain' => "CREATE TABLE IF NOT EXISTS `alias_domain` (`alias_domain` varchar(255) NOT NULL DEFAULT '', `target_domain` varchar(255) NOT NULL DEFAULT '', `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `modified` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `active` tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (`alias_domain`), KEY `active` (`active`), KEY `target_domain` (`target_domain`)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'captcha' => "CREATE TABLE IF NOT EXISTS `captcha` (`id` bigint(20) NOT NULL AUTO_INCREMENT, `time` int(11) NOT NULL, `code` char(5) NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;",
	'dmarc_report' => "CREATE TABLE IF NOT EXISTS `dmarc_report` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `report_id` varchar(255) NOT NULL, `email` varchar(255) NOT NULL, `org_name` varchar(255) NOT NULL, `begin` datetime NOT NULL, `end` datetime NOT NULL, `domain` varchar(255) NOT NULL, `adkim` char(1) NOT NULL, `aspf` char(1) NOT NULL, `policy` varchar(10) NOT NULL, `subdomain_policy` varchar(10) NOT NULL, `pct` tinyint(3) unsigned NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `report_id_org_name` (`report_id`,`org_name`), KEY `begin_end` (`begin`,`end`), KEY `domain` (`domain`), CONSTRAINT `dmarc_report_ibfk_1` FOREIGN KEY (`domain`) REFERENCES `domain` (`domain`) ON DELETE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'dmarc_report_errors' => "CREATE TABLE IF NOT EXISTS `dmarc_report_errors` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `report_id` bigint(20) unsigned NOT NULL, `error` text NOT NULL, PRIMARY KEY (`id`), KEY `report_id` (`report_id`), CONSTRAINT `dmarc_report_errors_ibfk_1` FOREIGN KEY (`id`) REFERENCES `dmarc_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'dmarc_report_records' => "CREATE TABLE IF NOT EXISTS `dmarc_report_records` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `report_id` bigint(20) unsigned NOT NULL, `source_ip` varchar(255) NOT NULL, `count` int(11) NOT NULL, `policy_evaluated_disposition` varchar(10) NOT NULL, `policy_evaluated_dkim` char(4) NOT NULL, `policy_evaluated_spf` char(4) NOT NULL, `identifier_envelope_to` varchar(255) NOT NULL, `identifier_envelope_from` varchar(255) NOT NULL, `identifier_header_from` varchar(255) NOT NULL, PRIMARY KEY (`id`), KEY `report_id` (`report_id`), CONSTRAINT `dmarc_report_records_ibfk_2` FOREIGN KEY (`report_id`) REFERENCES `dmarc_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'dmarc_report_record_dkim_result' => "CREATE TABLE IF NOT EXISTS `dmarc_report_record_dkim_result` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `record_id` bigint(20) unsigned NOT NULL, `domain` varchar(255) NOT NULL, `selector` varchar(255) NOT NULL, `result` varchar(255) NOT NULL, `human_result` varchar(255) NOT NULL, PRIMARY KEY (`id`), KEY `record_id` (`record_id`), CONSTRAINT `dmarc_report_record_dkim_result_ibfk_2` FOREIGN KEY (`record_id`) REFERENCES `dmarc_report_records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'dmarc_report_record_reason' => "CREATE TABLE IF NOT EXISTS `dmarc_report_record_reason` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `record_id` bigint(20) unsigned NOT NULL, `type` varchar(255) NOT NULL, `comment` varchar(255) NOT NULL, PRIMARY KEY (`id`), KEY `record_id` (`record_id`), CONSTRAINT `dmarc_report_record_reason_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `dmarc_report_records` (`id`) ON DELETE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'dmarc_report_record_spf_result' => "CREATE TABLE IF NOT EXISTS `dmarc_report_record_spf_result` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `record_id` bigint(20) unsigned NOT NULL, `domain` varchar(255) NOT NULL, `result` varchar(255) NOT NULL, `scope` varchar(255) NOT NULL, PRIMARY KEY (`id`), KEY `record_id` (`record_id`), CONSTRAINT `dmarc_report_record_spf_result_ibfk_2` FOREIGN KEY (`record_id`) REFERENCES `dmarc_report_records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE) DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;",
	'domain_admins' => "CREATE TABLE IF NOT EXISTS `domain_admins` (`username` varchar(255) NOT NULL, `domain` varchar(255) NOT NULL, `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `active` tinyint(1) NOT NULL DEFAULT 1, `id` bigint(20) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`), KEY `username` (`username`), KEY `active` (`active`), KEY `domain` (`domain`), CONSTRAINT `domain_admins_ibfk_1` FOREIGN KEY (`domain`) REFERENCES `domain` (`domain`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `domain_admins_ibfk_2` FOREIGN KEY (`username`) REFERENCES `admin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'mailbox' => "CREATE TABLE IF NOT EXISTS `mailbox` (`username` varchar(255) NOT NULL, `password` varchar(255) NOT NULL DEFAULT '', `quota` bigint(20) NOT NULL DEFAULT 0, `local_part` varchar(255) NOT NULL DEFAULT '', `domain` varchar(255) NOT NULL DEFAULT '', `created` datetime NOT NULL DEFAULT current_timestamp(), `modified` datetime NOT NULL DEFAULT current_timestamp(), `active` tinyint(1) NOT NULL DEFAULT 1, `password_hash_type` varchar(20) NOT NULL DEFAULT '', `openpgpkey_wkd` char(32) NOT NULL DEFAULT '', `pgp_key` text DEFAULT NULL, `pgp_verified` tinyint(1) NOT NULL DEFAULT 0, `tfa` tinyint(1) NOT NULL DEFAULT 0, `last_login` bigint(20) unsigned DEFAULT NULL, `enforce_tls_in` tinyint(1) NOT NULL DEFAULT 1, `enforce_tls_out` tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (`username`), KEY `domain` (`domain`), KEY `active` (`active`), KEY `openpgpkey_wkd` (`openpgpkey_wkd`), CONSTRAINT `mailbox_ibfk_2` FOREIGN KEY (`domain`) REFERENCES `domain` (`domain`) ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'monitored_mailboxes' => "CREATE TABLE IF NOT EXISTS `monitored_mailboxes` (`username` varchar(255) NOT NULL, `last_login` int(11) NOT NULL, PRIMARY KEY (`username`), KEY `lastlogin` (`last_login`), CONSTRAINT `monitored_mailboxes_ibfk_1` FOREIGN KEY (`username`) REFERENCES `mailbox` (`username`) ON DELETE CASCADE ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'tls_report' => "CREATE TABLE IF NOT EXISTS `tls_report` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `report_id` varchar(255) NOT NULL, `organization` varchar(255) NOT NULL, `contact` varchar(255) NOT NULL, `start` datetime NOT NULL, `end` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `report_id_organization` (`report_id`,`organization`), KEY `start_end` (`start`,`end`)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'tls_report_policy' => "CREATE TABLE IF NOT EXISTS `tls_report_policy` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `report_id` bigint(20) unsigned NOT NULL, `policy_type` varchar(255) NOT NULL, `policy_string` text NOT NULL, `policy_domain` varchar(255) NOT NULL, `mx_host` text NOT NULL, `success` int(11) NOT NULL, `failure` int(11) NOT NULL, PRIMARY KEY (`id`), KEY `report_id` (`report_id`), KEY `policy_domain` (`policy_domain`), CONSTRAINT `tls_report_policy_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `tls_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `tls_report_policy_ibfk_3` FOREIGN KEY (`policy_domain`) REFERENCES `domain` (`domain`) ON DELETE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'tls_report_policy_failures' => "CREATE TABLE IF NOT EXISTS `tls_report_policy_failures` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `policy_id` bigint(20) unsigned NOT NULL, `result_type` varchar(255) NOT NULL, `sender_ip` varchar(255) NOT NULL, `receiver_ip` varchar(255) NOT NULL, `receiver_hostname` varchar(255) NOT NULL, `reveiver_helo` varchar(255) NOT NULL, `session_count` int(11) NOT NULL, `additional_information` text NOT NULL, `reason_code` varchar(255) NOT NULL, PRIMARY KEY (`id`), KEY `policy_id` (`policy_id`), CONSTRAINT `tls_report_policy_failures_ibfk_2` FOREIGN KEY (`policy_id`) REFERENCES `tls_report_policy` (`id`) ON DELETE CASCADE ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
	'settings' => 'CREATE TABLE IF NOT EXISTS `settings` (`setting` varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL PRIMARY KEY, `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;',
];

try{
	$stmt=$db->query("SELECT value FROM settings WHERE setting='version';");
	$version=$stmt->fetch(PDO::FETCH_NUM)[0];
	try {
		foreach($createTableStatements as $tableName => $statement){
			if ($tableName !== 'settings') {
				$db->exec($statement);
			}
		}
		$db->beginTransaction();
		$stmt=$db->prepare("UPDATE `settings` SET `value`=? WHERE `setting`='version';");
		$stmt->execute([DBVERSION]);
		$db->commit();
		if($version < DBVERSION){
			echo _('Database has successfully been updated.') . PHP_EOL;
		} else {
			echo _('Database is already up-to-date.') . PHP_EOL;
		}
	} catch(PDOException $e){
		echo _('Error updating database:') . PHP_EOL;
		echo $e->getMessage() . PHP_EOL;
		if($db->inTransaction()){
			$db->rollBack();
		}
	}
} catch(PDOException){
	//create tables
	try {
		foreach($createTableStatements as $statement){
			$db->exec($statement);
		}
		$stmt=$db->prepare("INSERT INTO `settings` (`setting`, `value`) VALUES ('version', ?);");
		$stmt->execute([DBVERSION]);
		echo _('Database has successfully been set up.') . PHP_EOL;
	} catch(PDOException $e){
		echo _('Error setting up database:') . PHP_EOL;
		echo $e->getMessage() . PHP_EOL;
	}
}
try {
	$stmt = $db->prepare( 'INSERT IGNORE INTO `domain` (`domain`, `created`, `modified`) VALUES (?, NOW(), NOW())' );
	$stmt->execute( [ CLEARNET_SERVER ] );
	$stmt->execute( [ ONION_SERVER ] );
	$stmt = $db->prepare( 'INSERT IGNORE INTO `alias_domain` (`alias_domain`, `target_domain`, `created`, `modified`) VALUES (?, ?, NOW(), NOW())' );
	$stmt->execute( [ ONION_SERVER, CLEARNET_SERVER ] );
} catch( PDOException $e ) {
	echo _('Error adding primary domain:') . PHP_EOL;
	echo $e->getMessage() . PHP_EOL;
}
