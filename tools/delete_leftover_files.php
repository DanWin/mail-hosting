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
			echo "$domain does not seem to have any accounts, but has a directory. Consider deleting it.\n";
		} else {
			$accounts = array_diff( scandir( '/var/mail/vmail/' . basename( $domain ) ), array( '..', '.' ) );
			foreach ( $accounts as $account ) {
				if ( ! isset( $mailboxes[ $domain ][ $account ] ) && is_dir( '/var/mail/vmail/' . basename( $domain ) . '/' . basename( $account ) ) ) {
					exec( 'rm -r ' . escapeshellarg('/var/mail/vmail/' . basename( $domain ) . '/' . basename( $account )));
					echo "Deleted: /var/mail/vmail/" . basename( $domain ) . '/' . basename( $account ) . "\n";
				} elseif( is_file('/var/mail/vmail/' . basename( $domain ) . '/' . basename( $account ))){
					echo 'File found in mail directory location: "/var/mail/vmail/' . basename( $domain ) . '/' . basename( $account ) . "\". Consider deleting it.\n";
				}
			}
		}
	}
}
