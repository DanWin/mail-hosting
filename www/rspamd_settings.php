<?php
require_once( '../common_config.php' );

header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-store' );
header( 'Expires: 600' ); 

$token = $_GET[ 'token' ] ?? '';
if ( $_SERVER[ 'REQUEST_METHOD' ] !== 'GET' || ! hash_equals( RSPAMD_SETTINGS_MAP_TOKEN, $token ) ) {
	http_response_code( 403 );
	exit;
}

$db = get_db_instance();
$stmt = $db->query( 'SELECT username, spam_reject_score FROM mailbox WHERE active = 1 AND spam_reject_score != ?;' );
$stmt->execute( [ DEFAULT_SPAM_REJECT_SCORE ] );
$recipients_by_policy = [];
while ( $mailbox = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
	if ( (int) $mailbox[ 'spam_reject_score' ] > 0 ) {
		$policy = 'enabled_' . (int) $mailbox[ 'spam_reject_score' ];
	} else {
		$policy = 'disabled';
	}
	$recipients_by_policy[ $policy ][] = mb_strtolower( $mailbox[ 'username' ] );
}

$settings = [];
foreach ( $recipients_by_policy as $policy => $recipients ) {
	$setting = [
		'id' => 'mailbox_' . $policy,
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

$output = [ 'settings {' ];
foreach ( $settings as $name => $setting ) {
	$output[] = "\t" . $name . ' {';
	$output[] = "\t\tid = " . json_encode( $setting[ 'id' ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ) . ';';
	$output[] = "\t\tpriority = " . $setting[ 'priority' ] . ';';
	$recipients = array_map(
		static fn( string $recipient ): string => json_encode( $recipient, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ),
		$setting[ 'rcpt' ]
	);
	$output[] = "\t\trcpt = [" . implode( ', ', $recipients ) . '];';
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
$output[] = '}';

echo implode( "\n", $output ) . "\n" . CUSTOM_RSPAMD_SETTINGS . "\n";
