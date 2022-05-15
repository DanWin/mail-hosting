<?php
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
const DBHOST = 'localhost'; // Database host
const DBUSER = 'postfix_readonly'; // Database user
const DBPASS = 'YOUR_PASSWORD'; // Database password
const DBNAME = 'postfix'; // Database

try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>false]);
}catch(PDOException $e){
	die('No Connection to MySQL database!');
}
$stmt = $db->query('SELECT username FROM mailbox WHERE active = 1;');
$all_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$count = count($all_accounts);
$i = 0;
foreach($all_accounts as $account){
	// skip to account x if script was aborted
	if(++$i < 1){
		continue;
	}
	echo "Sending mail to $account[username] ($i of $count)...\n";
	$mail = new PHPMailer(true);
	$mail->isSMTP();
	$mail->Host = '127.0.0.1';
	$mail->SMTPAuth = true;
	$mail->Username = 'YOUR_SMTP_USER';
	$mail->Password = 'YOUR_SMTP_PASSWORD';
	$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
	$mail->Port = 465;
	$mail->SMTPOptions = [
		'ssl' => [
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true,
		]
	];
	$mail->setFrom('YOUR_SMTP_USER', 'YOUR_NAME');
	$mail->Subject = 'YOUR_SUBJECT';
	$mail->Body    = 'YOUR_MESSAGE';
	try {
		$mail->addAddress($account['username']);
		$mail->send();
		$mail->clearAddresses();
	} catch (Exception $e) {
		file_put_contents(__DIR__.'/failed.txt', "Sending mail to $account[username] ($i of $count)...\nMessage could not be sent. Mailer Error: {$mail->ErrorInfo}", FILE_APPEND);
	}
}
