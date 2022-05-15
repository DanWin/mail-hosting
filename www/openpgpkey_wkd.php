<?php
require_once '../common_config.php';
header( 'Access-Control-Allow-Origin: *' );
$db = get_db_instance();
$stmt = $db->prepare( 'SELECT pgp_key FROM mailbox WHERE openpgpkey_wkd = ? AND domain = ?;' );
$stmt->execute( [ explode( '?', basename( $_SERVER[ 'REQUEST_URI' ] ) )[ 0 ], $_GET[ 'domain' ] ?? $_SERVER[ 'HTTP_HOST' ] ] );
$res = $stmt->fetch( PDO::FETCH_ASSOC );
if ( ! empty( $res[ 'pgp_key' ] ) ) {
	$gpg = gnupg_init();
	gnupg_seterrormode( $gpg, GNUPG_ERROR_WARNING );
	gnupg_setarmor( $gpg, 0 );
	$imported_key = gnupg_import( $gpg, $res[ 'pgp_key' ] );
	if ( ! $imported_key ) {
		http_response_code( 500 );
	} else {
		http_response_code( 200 );
		header( 'Content-Type: application/octet-stream' );
		echo gnupg_export( $gpg, $imported_key[ 'fingerprint' ] );
	}
} else {
	http_response_code( 404 );
}
