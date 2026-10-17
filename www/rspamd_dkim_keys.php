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

$stmt = get_db_instance()->query( "SELECT domain, dkim_key, dkim_selector FROM domain WHERE active = 1 ORDER BY domain;" );
$map_entries = [];
while ( $domain = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
	if(empty($domain['dkim_key'])) {
		continue; // Skip domains without DKIM keys
	}
	$ascii_domain = idn_to_ascii( $domain[ 'domain' ], IDNA_NONTRANSITIONAL_TO_ASCII );
	$key = get_rsa_dkim_private_key_base64_der( $domain[ 'dkim_key' ] );
	if ( ! is_string( $ascii_domain ) || ! preg_match( '/^[a-z0-9.-]+$/iD', $ascii_domain ) || $key === null || ! preg_match( '/^[a-zA-Z0-9_-]+$/D', $domain[ 'dkim_selector' ] ) ) {
		http_response_code( 500 );
		error_log( 'Invalid RSA DKIM key, selector, or domain configured for ' . $domain[ 'domain' ] );
		exit;
	}
	$ascii_domain = mb_strtolower( $ascii_domain );
	$map_entries[] = $ascii_domain . ' %:' . $domain[ 'dkim_selector' ] . ':' . $key;
	$map_entries[] = '*@' . $ascii_domain . ' ' . $ascii_domain;
}

echo implode( "\n", $map_entries ) . ( $map_entries ? "\n" : '' );
