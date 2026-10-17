<?php
require_once __DIR__ . '/../common_config.php';

function handle_postfix_policy_request(): bool
{
	$attributes = [];
	while ( ( $line = fgets( STDIN ) ) !== false ) {
		$line = rtrim( $line, "\r\n" );
		if ( $line === '' ) {
			if ( $attributes ) {
				break;
			}
			continue;
		}
		$separator = strpos( $line, '=' );
		if ( $separator !== false ) {
			$attributes[ substr( $line, 0, $separator ) ] = substr( $line, $separator + 1 );
		}
	}
	if ( ! $attributes ) {
		return false;
	}

	if (
		( $attributes[ 'request' ] ?? '' ) === 'smtpd_access_policy'
		&& ( $attributes[ 'protocol_state' ] ?? '' ) === 'MAIL'
		&& is_string( $attributes[ 'sasl_username' ] ?? null )
		&& preg_match( '/^[^@\s]+@[^@\s]+$/D', $attributes[ 'sasl_username' ] )
	) {
		try {
			$db = new PDO(
				'mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4',
				DBUSER,
				DBPASS,
				[ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT => PERSISTENT, PDO::ATTR_TIMEOUT => 5 ]
			);
			$stmt = $db->prepare( 'UPDATE mailbox SET last_login = ? WHERE username = ? AND active = 1;' );
			$stmt->execute( [ time(), mb_strtolower( $attributes[ 'sasl_username' ] ) ] );
		} catch ( PDOException $exception ) {
			error_log( 'Unable to update mailbox last_login from Postfix policy request: ' . $exception->getMessage() );
		}
	}

	fwrite( STDOUT, "action=DUNNO\n\n" );
	return true;
}

while ( handle_postfix_policy_request() ) {
}
