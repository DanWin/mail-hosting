<?php
require_once( '../common_config.php' );

header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-store' );
header( 'Expires: 600' );

$token = is_string( $_GET[ 'token' ] ?? null ) ? $_GET[ 'token' ] : '';
if ( ! in_array( $_SERVER[ 'REQUEST_METHOD' ], [ 'GET', 'HEAD' ], true ) || ! hash_equals( RSPAMD_SETTINGS_MAP_TOKEN, $token ) ) {
	http_response_code( 403 );
	exit;
}

$stmt = get_db_instance()->query( "SELECT username, trusted FROM mailbox WHERE active = 1 AND (trusted = 1 OR created >= DATE_SUB(NOW(), INTERVAL 14 DAY)) ORDER BY username;" );
while ( $mailbox = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
	$classification = (int) $mailbox[ 'trusted' ] === 1 ? 'TRUSTED' : 'NEW';
	echo mb_strtolower( $mailbox[ 'username' ] ) . "\t" . $classification . "\n";
}
