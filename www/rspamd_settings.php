<?php
require_once( '../common_config.php' );

header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-store' );
header( 'Expires: 600' ); 

$token = $_GET[ 'token' ] ?? '';
if ( !in_array( $_SERVER[ 'REQUEST_METHOD' ], [ 'GET', 'HEAD' ], true ) || ! hash_equals( RSPAMD_SETTINGS_MAP_TOKEN, $token ) ) {
	http_response_code( 403 );
	exit;
}

$db = get_db_instance();
$stmt = $db->prepare( "SELECT username, spam_reject_score, blocked_senders, whitelisted_senders FROM mailbox WHERE active = 1 AND (spam_reject_score != ? OR blocked_senders != '' OR whitelisted_senders != '');" );
$stmt->execute( [ DEFAULT_SPAM_REJECT_SCORE ] );
$recipients_by_policy = [];
$sender_settings = [];
$sender_rule_index = 0;
while ( $mailbox = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
	if ( (int) $mailbox[ 'spam_reject_score' ] !== DEFAULT_SPAM_REJECT_SCORE ) {
		if ( (int) $mailbox[ 'spam_reject_score' ] > 0 ) {
			$policy = 'enabled_' . (int) $mailbox[ 'spam_reject_score' ];
		} else {
			$policy = 'disabled';
		}
		$recipients_by_policy[ $policy ][] = mb_strtolower( $mailbox[ 'username' ] );
	}

	$blocked_senders = array_filter( array_map( 'trim', preg_split( '/\R/', $mailbox[ 'blocked_senders' ] ) ) );
	$whitelisted_senders = array_filter( array_map( 'trim', preg_split( '/\R/', $mailbox[ 'whitelisted_senders' ] ) ) );
	$whitelisted_sender_keys = array_fill_keys( array_map( 'mb_strtolower', $whitelisted_senders ), true );
	foreach ( $blocked_senders as $sender ) {
		if ( isset( $whitelisted_sender_keys[ mb_strtolower( $sender ) ] ) ) {
			continue;
		}
		$sender_rule_index++;
		$sender_settings[ 'blocked_sender_' . $sender_rule_index ] = [
			'priority' => 'high',
			'from' => $sender,
			'rcpt' => mb_strtolower( $mailbox[ 'username' ] ),
			'apply' => [ 'actions' => [ 'reject' => -999 ] ],
		];
	}
	foreach ( $whitelisted_senders as $sender ) {
		$sender_rule_index++;
		$sender_settings[ 'whitelisted_sender_' . $sender_rule_index ] = [
			'priority' => 'high',
			'from' => $sender,
			'rcpt' => mb_strtolower( $mailbox[ 'username' ] ),
			'want_spam' => true,
		];
	}
}

$settings = [];
foreach ( $recipients_by_policy as $policy => $recipients ) {
	$setting = [
		'priority' => 'low',
		'rcpt' => $recipients,
	];
	if ( $policy === 'disabled' ) {
		$setting[ 'want_spam' ] = true;
	} else {
		$setting[ 'apply' ] = [
			'actions' => [
				'reject' => (float) substr( $policy, strlen( 'enabled_' ) ),
			],
		];
	}
	$settings[ $policy ] = $setting;
}
$settings += $sender_settings;

$output = [ 'settings {' ];
foreach ( $settings as $name => $setting ) {
	$output[] = "\t" . $name . ' {';
	$output[] = "\t\tpriority = " . $setting[ 'priority' ] . ';';
	if ( isset( $setting[ 'from' ] ) ) {
		$output[] = "\t\tfrom = " . json_encode( $setting[ 'from' ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ) . ';';
		$output[] = "\t\trcpt = " . json_encode( $setting[ 'rcpt' ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ) . ';';
	} else {
		$recipients = array_map(
			static fn( string $recipient ): string => json_encode( $recipient, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ),
			$setting[ 'rcpt' ]
		);
		$output[] = "\t\trcpt = [" . implode( ', ', $recipients ) . '];';
	}
	if ( isset( $setting[ 'want_spam' ] ) ) {
		$output[] = "\t\twant_spam = yes;";
	} else {
		$output[] = "\t\tapply {";
		$output[] = "\t\t\tactions {";
		$output[] = "\t\t\t\treject = " . $setting[ 'apply' ][ 'actions' ][ 'reject' ] . ';';
		$output[] = "\t\t\t}";
		$output[] = "\t\t}";
	}
	$output[] = "\t}";
}
$output[] = CUSTOM_RSPAMD_SETTINGS;
$output[] = '}';

echo implode( "\n", $output ) . "\n";
