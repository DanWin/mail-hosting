<?php
/**
 * This script is used to export a mailbox for a specific email address.
 * It retrieves the mailbox information from the database, copies the mailbox files,
 * decrypts them, and creates a tar.gz archive of the mailbox.
 */
const EXPORT_EMAIL = 'email_to_export@danwin1210.de';
require_once __DIR__ . '/../common_config.php';
$db = get_db_instance();
$stmt = $db->prepare('SELECT a.goto, m.domain, m.local_part, m.created, m.modified, m.last_login FROM `mailbox` AS m LEFT JOIN `alias` AS a ON (a.address=m.username) WHERE m.`username` = ?;');
$stmt->execute([EXPORT_EMAIL]);
if($result = $stmt->fetch(PDO::FETCH_ASSOC)){
	$exportText = [
		"Forwarding addresses: ".$result['goto'],
		"Created: ".$result['created']." UTC",
		"Modified: ".$result['modified']." UTC",
		"Last login: ".date('Y-m-d H:i:s', $result['last_login'])." UTC",
	];
	$mailboxPath = '/var/mail/vmail/'.$result['domain'].'/'.$result['local_part'];
	$infoFile = $result['local_part'].'_mailbox_info.txt';
	$xmppFile = $result['local_part'].'_prosody_dump.txt';
	file_put_contents($infoFile, implode(PHP_EOL, $exportText).PHP_EOL);
	try {
		$dbProsody = new PDO('mysql:host=' . DBHOST_PROSODY . ';dbname=' . DBNAME_PROSODY, DBUSER_PROSODY, DBPASS_PROSODY, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		$lines = [
			'Prosody user data dump for host='.$result['domain'].' and user='.$result['local_part'],
			'',
			'Table: prosody',
		];
		$stmtProsody = $dbProsody->prepare('SELECT * FROM `prosody` WHERE `host` = ? AND `user` = ?;');
		$stmtProsody->execute([$result['domain'], $result['local_part']]);
		while($row = $stmtProsody->fetch(PDO::FETCH_ASSOC)){
			$lines[] = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, 512);
		}
		$lines[] = '';
		$lines[] = 'Table: prosodyarchive';
		$stmtArchive = $dbProsody->prepare('SELECT * FROM `prosodyarchive` WHERE `host` = ? AND `user` = ? ORDER BY `when`;');
		$stmtArchive->execute([$result['domain'], $result['local_part']]);
		while($row = $stmtArchive->fetch(PDO::FETCH_ASSOC)){
			$lines[] = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, 512);
		}
		file_put_contents($xmppFile, implode(PHP_EOL, $lines).PHP_EOL);
	} catch (PDOException $e) {
		file_put_contents($xmppFile, "Prosody dump failed: ".$e->getMessage().PHP_EOL);
	}
	if(file_exists($mailboxPath)){
		exec('cp -a '.escapeshellarg($mailboxPath) . ' . && sync');
		exec('./crypt_maildir.sh '.escapeshellarg($result['local_part']) . ' decrypt && sync');
		sleep(15);
		exec('tar czf '.escapeshellarg($result['local_part'].'.tar.gz') . ' ' . escapeshellarg($result['local_part']) . ' ' . escapeshellarg($infoFile) . ' ' . escapeshellarg($xmppFile));
		exec('rm -r '.escapeshellarg($result['local_part']));
		@unlink($infoFile);
		@unlink($xmppFile);
	}
}else {
	echo "E-Mail doesn't exist\n";
}
