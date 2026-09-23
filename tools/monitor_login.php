<?php
/**
 * This script is used to monitor the last login times of specific email addresses.
 * It checks the last login time of each monitored email address and compares it with the last recorded login time in the `monitored_mailboxes` table.
 * If a new login is detected, it updates the last login time in the database and sends a notification email to the specified recipients.
 */
const NOTIFICATION_EMAIL = 'police@example.com';
const MONITOR_MAILS = ['criminal@danwin1210.de', 'another_criminal@danwin1210.de'];
require_once __DIR__ . '/../common_config.php';
$db = get_db_instance();
$stmt = $db->prepare( "SELECT `m`.`last_login` FROM `mailbox` AS m LEFT JOIN `monitored_mailboxes` AS `mon` ON (`m`.`username`=`mon`.`username`) WHERE `m`.`username` = ? AND (ISNULL(`mon`.`last_login`) OR `mon`.`last_login` < `m`.`last_login`);" );
$update = $db->prepare( "INSERT INTO `monitored_mailboxes` (`username`, `last_login`) VALUES(?, ?) ON DUPLICATE KEY UPDATE `last_login` = ?;" );
$changed = '';
foreach(MONITOR_MAILS as $mail){
	$stmt->execute([$mail]);
	if($result = $stmt->fetch(PDO::FETCH_ASSOC)){
		$update->execute([$mail, $result['last_login'], $result['last_login']]);
		$changed .= $mail . ' -> ' . date('Y-m-d H:i:s', $result['last_login']) . ' UTC' . PHP_EOL;
	}
}
if(!empty($changed)){
	mail(NOTIFICATION_EMAIL, 'User logged in', $changed."\n\n");
}
