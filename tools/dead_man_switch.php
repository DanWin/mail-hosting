<?php
require_once __DIR__ . '/../common_config.php';

if ( ! DEAD_MAN_SWITCH_ENABLED ) {
	return;
}

function dead_man_switch_validate_email( string $email ): void
{
	if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) === false ) {
		throw new RuntimeException( sprintf( _('Invalid email address in dead man switch configuration: %s'), $email ) );
	}
}

dead_man_switch_validate_email( DEAD_MAN_SWITCH_MAILBOX );

if ( empty(DEAD_MAN_SWITCH_TRUSTED_CONTACTS) ) {
	throw new RuntimeException( _('At least one trusted contact must be configured.') );
}

foreach ( DEAD_MAN_SWITCH_TRUSTED_CONTACTS as $contact ) {
	dead_man_switch_validate_email( $contact );
}

foreach ( DEAD_MAN_SWITCH_CUSTOMER_CONTACTS as $customer ) {
	dead_man_switch_validate_email( $customer );
}

$db = get_db_instance();
$stmt = $db->prepare( 'SELECT `last_login`, UNIX_TIMESTAMP(`created`) AS `created_at` FROM `mailbox` WHERE `username` = ? AND `active` = 1;' );
$stmt->execute( [ DEAD_MAN_SWITCH_MAILBOX ] );
$monitored_mailbox = $stmt->fetch( PDO::FETCH_ASSOC );
if ( ! $monitored_mailbox ) {
	throw new RuntimeException( sprintf( _('The configured dead man switch mailbox does not exist or is inactive: %s'), DEAD_MAN_SWITCH_MAILBOX ) );
}
$last_activity = (int) ( $monitored_mailbox['last_login'] ?? $monitored_mailbox['created_at'] );

$settings_key = 'dead_man_switch';
$stmt = $db->prepare( 'SELECT `value` FROM `settings` WHERE `setting` = ?;' );
$stmt->execute( [ $settings_key ] );
$stored_state = $stmt->fetchColumn();
if ( $stored_state === false ) {
	$state = [
		'mailbox' => DEAD_MAN_SWITCH_MAILBOX,
		'last_activity' => $last_activity,
		'notified_stage' => 0,
		'last_run' => 0,
	];
} else {
	$state = json_decode( $stored_state, true, 512, JSON_THROW_ON_ERROR );
	if ( !is_array($state) || $state['mailbox'] !== DEAD_MAN_SWITCH_MAILBOX || $state['last_activity'] !== $last_activity ) {
		$state = [
			'mailbox' => DEAD_MAN_SWITCH_MAILBOX,
			'last_activity' => $last_activity,
			'notified_stage' => 0,
			'last_run' => 0,
		];
	}
}
$notified_stage = $state['notified_stage'];

$elapsed = time() - $last_activity;
$stage = $notified_stage + 1;
$notification = DEAD_MAN_SWITCH_STAGES[$stage] ?? null;
$stage_interval = $notification !== null && $stage > 1
	? $notification['after'] - DEAD_MAN_SWITCH_STAGES[$notified_stage]['after']
	: 0;
if (
	$notification !== null
	&& $elapsed >= $notification['after']
	&& ( $state['last_run'] === 0 || time() - $state['last_run'] >= $stage_interval )
) {
	foreach ( $notification['recipients'] as $recipient ) {
		if ( ! mail( $recipient, $notification['subject'], $notification['body'], "Content-Type: text/plain; charset=UTF-8\r\n" ) ) {
			throw new RuntimeException( sprintf( _('Unable to send dead man switch notification to %s.'), $recipient ) );
		}
	}
	$state['notified_stage'] = $stage;
	$state['last_run'] = time();
}

$stmt = $db->prepare( 'INSERT INTO `settings` (`setting`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);' );
$stmt->execute( [ $settings_key, json_encode( $state, JSON_THROW_ON_ERROR ) ] );
