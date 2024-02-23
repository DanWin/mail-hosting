<?php
require_once __DIR__ . '/../common_config.php';
$db = get_db_instance();
$stmt = $db->query( "select local_part, domain from mailbox;" );
$mailboxes = [];
while ( $mailbox = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
	$mailboxes[ $mailbox[ 'domain' ] ][ $mailbox[ 'local_part' ] ] = true;
}
$domains = array_diff( scandir( '/var/mail/vmail/' ), array( '..', '.' ) );
$dirs = [];
foreach ( $domains as $domain ) {
	if ( is_dir( '/var/mail/vmail/' . basename( $domain ) ) ) {
		if ( ! isset( $mailboxes[ $domain ] ) ) {
			echo sprintf(_('%s does not seem to have any accounts, but has a directory. Consider deleting it.'), $domain).PHP_EOL;
		} else {
			$accounts = array_diff( scandir( '/var/mail/vmail/' . basename( $domain ) ), array( '..', '.' ) );
			foreach ( $accounts as $account ) {
				if ( ! isset( $mailboxes[ $domain ][ $account ] ) && is_dir( '/var/mail/vmail/' . basename( $domain ) . '/' . basename( $account ) ) ) {
					exec( 'rm -r ' . escapeshellarg('/var/mail/vmail/' . basename( $domain ) . '/' . basename( $account )));
					echo sprintf(_('Deleted: %s'), '/var/mail/vmail/' . basename( $domain ) . '/' . basename( $account )) . PHP_EOL;
				} elseif( is_file('/var/mail/vmail/' . basename( $domain ) . '/' . basename( $account ))){
					echo sprintf(_('File found in mail directory location: "%s". Consider deleting it.'), '/var/mail/vmail/'. basename( $domain ) . '/' . basename( $account ) ) . PHP_EOL;
				}
			}
		}
	}
}
$domains = array_diff( scandir( '/var/local/snappymail/_data_/_default_/storage/' ), array( '..', '.', '__nobody__' ) );
$dirs = [];
foreach ( $domains as $domain ) {
	if ( is_dir( '/var/local/snappymail/_data_/_default_/storage/' . basename( $domain ) ) ) {
		if ( ! isset( $mailboxes[ $domain ] ) ) {
			echo sprintf(_('%s does not seem to have any accounts, but has a snappymail directory. Consider deleting it.'), $domain).PHP_EOL;
		} else {
			$accounts = array_diff( scandir( '/var/local/snappymail/_data_/_default_/storage/' . basename( $domain ) ), array( '..', '.' ) );
			foreach ( $accounts as $account ) {
				if ( ! isset( $mailboxes[ $domain ][ $account ] ) && is_dir( '/var/local/snappymail/_data_/_default_/storage/' . basename( $domain ) . '/' . basename( $account ) ) ) {
					exec( 'rm -r ' . escapeshellarg('/var/local/snappymail/_data_/_default_/storage/' . basename( $domain ) . '/' . basename( $account )));
					echo sprintf(_('Deleted: %s'), '/var/local/snappymail/_data_/_default_/storage/' . basename( $domain ) . '/' . basename( $account )) . PHP_EOL;
				} elseif( is_file('/var/local/snappymail/_data_/_default_/storage/' . basename( $domain ) . '/' . basename( $account ))){
					echo sprintf(_('File found in mail directory location: "%s". Consider deleting it.'), '/var/local/snappymail/_data_/_default_/storage/'. basename( $domain ) . '/' . basename( $account ) ) . PHP_EOL;
				}
			}
		}
	}
}
$accout_files = array_diff( scandir( '/var/local/squirrelmail/data/' ), array( '..', '.' ) );
foreach( $accout_files as $file ){
	if(preg_match( '/^(.+?)(@(.+))?\.(pref|abook|sig)$/', $file, $matches )){
		$domain = $matches[ 3 ];
		if(in_array($domain, ['', 'danielas3rtn54uwmofdo3x2bsdifr47huasnmbgqzfrec5ubupvtpid.onion'], true)){
			$domain = 'danwin1210.de';
		}
		$account = $matches[ 1 ];
		if ( ! isset( $mailboxes[ $domain ][ $account ] )){
			unlink('/var/local/squirrelmail/data/' . $file);
			echo sprintf(_('Deleted: %s'), '/var/local/squirrelmail/data/' . $file) . PHP_EOL;
		}
	}
}
