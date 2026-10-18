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

$stmt = get_db_instance()->query( 'SELECT domain FROM domain WHERE active = 1 ORDER BY domain;' );
$domains = [];
while ( $domain = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
	$ascii_domain = idn_to_ascii( $domain[ 'domain' ], IDNA_NONTRANSITIONAL_TO_ASCII );
	if ( ! is_string( $ascii_domain ) || ! preg_match( '/^[a-z0-9.-]+$/iD', $ascii_domain ) ) {
		http_response_code( 500 );
		error_log( 'Invalid local domain configured for Rspamd: ' . $domain[ 'domain' ] );
		exit;
	}
	$domains[ mb_strtolower( $ascii_domain ) ] = true;
}

foreach ( array_keys( $domains ) as $domain ) {
	echo $domain . "\t1\n";
}
