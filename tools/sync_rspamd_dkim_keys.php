<?php

require_once __DIR__ . '/../common_config.php';

function sync_rspamd_dkim_keys( PDO $db ): int
{
	if ( ! extension_loaded( 'redis' ) ) {
		throw new RuntimeException( 'The php-redis extension is required to synchronize DKIM signing keys.' );
	}

	$stmt = $db->query( "SELECT domain, dkim_key, dkim_selector FROM `domain` WHERE active = 1 ORDER BY domain;" );
	$domains = [];
	while ( $domain = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
		if ( empty($domain[ 'dkim_key' ]) || empty($domain[ 'dkim_selector' ]) || str_ends_with($domain['domain'], '.onion') ) {
			continue;
		}

		$asciiDomain = idn_to_ascii( $domain[ 'domain' ], IDNA_NONTRANSITIONAL_TO_ASCII );
		if ( ! is_string( $asciiDomain ) || ! preg_match( '/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/iD', $asciiDomain ) ) {
			throw new RuntimeException( 'Invalid domain configured for DKIM signing: ' . $domain[ 'domain' ] );
		}
		$asciiDomain = strtolower( $asciiDomain );
		if ( isset( $domains[ $asciiDomain ] ) ) {
			throw new RuntimeException( 'Duplicate ASCII domain configured for DKIM signing: ' . $asciiDomain );
		}
		$privateKey = openssl_pkey_get_private( $domain[ 'dkim_key' ] );
		$keyDetails = $privateKey === false ? false : openssl_pkey_get_details( $privateKey );
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/D', $domain[ 'dkim_selector' ] ) || $keyDetails === false || $keyDetails[ 'type' ] !== OPENSSL_KEYTYPE_RSA ) {
			throw new RuntimeException( 'Invalid DKIM signing selector or private key for domain: ' . $asciiDomain );
		}
		$domains[ $asciiDomain ] = [
			'key' => $domain[ 'dkim_key' ],
			'selector' => $domain[ 'dkim_selector' ],
		];
	}

	$redis = new Redis();

	try {
		if ( ! $redis->connect( RSPAMD_REDIS_HOST, RSPAMD_REDIS_PORT, 3.0 ) ) {
			throw new RuntimeException( 'Could not connect to Rspamd Redis.' );
		}
		if ( ! $redis->setOption( Redis::OPT_READ_TIMEOUT, 5.0 ) ) {
			throw new RuntimeException( 'Could not set the Redis read timeout.' );
		}
		if ( $redis->multi() === false ) {
			throw new RuntimeException( 'Redis did not start the DKIM key synchronization transaction.' );
		}
		$redis->del( 'dkim_keys', 'dkim_selectors' );
		foreach ( $domains as $domain => $signingKey ) {
			$redis->hSet( 'dkim_keys', $signingKey[ 'selector' ] . '.' . $domain, $signingKey[ 'key' ] );
			$redis->hSet( 'dkim_selectors', $domain, $signingKey[ 'selector' ] );
		}
		$results = $redis->exec();
		if ( ! is_array( $results ) || count( $results ) !== 1 + ( count( $domains ) * 2 ) || array_filter( $results, 'is_int' ) !== $results ) {
			throw new RuntimeException( 'Redis did not complete DKIM key synchronization.' );
		}
	} catch ( RedisException $exception ) {
		throw new RuntimeException( 'Failed to synchronize DKIM signing keys with Redis: ' . $exception->getMessage(), 0, $exception );
	} finally {
		$redis->close();
	}

	return count( $domains );
}

if ( PHP_SAPI === 'cli' && realpath( $_SERVER[ 'SCRIPT_FILENAME' ] ?? '' ) === __FILE__ ) {
	try {
		$count = sync_rspamd_dkim_keys( get_db_instance() );
		echo sprintf( "Synchronized %d DKIM signing keys to Redis.\n", $count );
	} catch ( PDOException | RuntimeException $exception ) {
		fwrite( STDERR, 'Failed to synchronize DKIM signing keys: ' . $exception->getMessage() . PHP_EOL );
		exit( 1 );
	}
}
