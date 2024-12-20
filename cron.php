<?php
if ( php_sapi_name() !== 'cli' ) {
	exit;
}

require_once 'common_config.php';
$db = get_db_instance();
try {
	$db_prosody = new PDO( 'mysql:host=' . DBHOST_PROSODY . ';dbname=' . DBNAME_PROSODY, DBUSER_PROSODY, DBPASS_PROSODY, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
} catch ( PDOException $e ) {
	die( _('No Connection to MySQL database!') . PHP_EOL);
}
setlocale( LC_CTYPE, 'C.UTF-8' ); // make sure to use UTF-8 locale. Non UTF-8 locales can cause serious issues when handling UTF-8 file names

// mark accounts deletable that haven't been used in an entire year
$expire_time = strtotime( '-1 year' );
$stmt = $db->prepare( 'UPDATE mailbox SET active = -2 WHERE active IN (0, 1) AND modified < ? AND (last_login < ? OR last_login IS NULL);' );
$stmt->execute( [ date( 'Y-m-d H:i:s', $expire_time ), $expire_time ] );

// delete all associated data when deleting/disabling accounts
$stmt = $db->query( 'SELECT username, local_part, domain, active FROM mailbox WHERE active IN (-1, -2);' );
$disable = $db->prepare( 'UPDATE mailbox SET active = 0 WHERE username = ?;' );
$delete = $db->prepare( 'DELETE FROM mailbox WHERE username = ?;' );
$delete_alias = $db->prepare( 'DELETE FROM alias WHERE address = ?;' );
$delete_prosody = $db_prosody->prepare( 'DELETE FROM prosody WHERE user = ? AND host = ?;' );
$delete_prosody_archive = $db_prosody->prepare( 'DELETE FROM prosodyarchive WHERE user = ? AND host = ?;' );
while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
	$domain_basename = basename( $tmp[ 'domain' ] );
	$local_basename = basename( $tmp[ 'local_part' ] );
	if ( ! in_array( $domain_basename, [ '..', '.', '' ], true ) && ! in_array( $local_basename, [ '..', '.', '' ], true ) ) {
		$mail_files = '/var/mail/vmail/' . $domain_basename . '/' . $local_basename;
		if ( file_exists( $mail_files ) ) {
			exec( 'rm -r ' . escapeshellarg( $mail_files ) );
		}
		$snapmail_files = '/var/local/snappymail/_data_/_default_/storage/' . $domain_basename . '/' . $local_basename;
		if ( file_exists( $snapmail_files ) ) {
			exec( 'rm -r ' . escapeshellarg( $snapmail_files ) );
		}
		$files = glob( '/var/local/squirrelmail/data/' . $local_basename . '@' . $domain_basename . '.{pref,abook,sig}', GLOB_BRACE );
		if ( $tmp[ 'domain' ] === CLEARNET_SERVER ) {
			$files = array_merge( $files, glob( '/var/local/squirrelmail/data/' . $local_basename . '{@'.ONION_SERVER.',}.{pref,abook,sig}', GLOB_BRACE ) );
			$delete_prosody->execute( [ $tmp[ 'local_part' ], $tmp[ 'domain' ] ] );
			$delete_prosody_archive->execute( [ $tmp[ 'local_part' ], $tmp[ 'domain' ] ] );
		}
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				@unlink( $file );
			}
		}
	}
	if ( $tmp[ 'active' ] === -2 ) {
		$delete->execute( [ $tmp[ 'username' ] ] );
	}
	if ( $tmp[ 'active' ] === -1 ) {
		$disable->execute( [ $tmp[ 'username' ] ] );
	}
	$delete_alias->execute( [ $tmp[ 'username' ] ] );
}
$stmt = $db->query( 'SELECT domain FROM domain WHERE active = -1;' );
$del_domain = $db->prepare( 'DELETE FROM domain WHERE domain = ?;' );
$del_mailbox = $db->prepare( 'DELETE FROM mailbox WHERE domain = ?;' );
$del_alias = $db->prepare( 'DELETE FROM alias WHERE domain = ?;' );
while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
	$domain_basename = basename( $tmp[ 'domain' ] );
	if ( ! in_array( $domain_basename, [ '..', '.', '' ], true ) ) {
		$del_alias->execute( [ $tmp[ 'domain' ] ] );
		$del_mailbox->execute( [ $tmp[ 'domain' ] ] );
		$files = glob( '/var/local/squirrelmail/data/*@' . $domain_basename . '.{pref,abook,sig}', GLOB_BRACE );
		$mail_files = '/var/mail/vmail/' . $domain_basename . '/';
		if ( file_exists( $mail_files ) ) {
			exec( 'rm -r ' . escapeshellarg( $mail_files ) );
		}
		$snapmail_files = '/var/local/snappymail/_data_/_default_/storage/' . $domain_basename . '/';
		if ( file_exists( $snapmail_files ) ) {
			exec( 'rm -r ' . escapeshellarg( $snapmail_files ) );
		}
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				@unlink( $file );
			}
		}
		$del_domain->execute( [ $tmp[ 'domain' ] ] );
	}
}
// delete squirrelmail attachments older than an hour
exec( 'find /var/local/squirrelmail/attach/ -type f -cmin +60 -delete' );
