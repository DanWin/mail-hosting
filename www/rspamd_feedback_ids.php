<?php
require_once( '../common_config.php' );

header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-store' );
header( 'Expires: 600' ); 

$token = $_GET[ 'token' ] ?? '';
if ( ! in_array( $_SERVER[ 'REQUEST_METHOD' ], [ 'GET', 'HEAD' ], true ) || ! hash_equals( RSPAMD_SETTINGS_MAP_TOKEN, $token ) ) {
	http_response_code( 403 );
	exit;
}

$db = get_db_instance();
$stmt = $db->query( 'SELECT username, feedback_id FROM mailbox WHERE active = 1;' );
while ( $mailbox = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
	if ( ! preg_match( '/^[a-f0-9]{5,15}$/', $mailbox[ 'feedback_id' ] ) ) {
		http_response_code( 500 );
		error_log( 'Invalid Google Feedback Loop ID for mailbox ' . $mailbox[ 'username' ] );
		exit;
	}
	echo mb_strtolower( $mailbox[ 'username' ] ) . "\t" . $mailbox[ 'feedback_id' ] . "\n";
}
