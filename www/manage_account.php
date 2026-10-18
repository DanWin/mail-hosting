<?php
require_once( '../common_config.php' );
global $language, $dir, $locale;
session_start();
if ( empty( $_SESSION[ 'csrf_token' ] ) ) {
	$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
}
$msg = '';
if ( ! empty( $_SESSION[ 'email_user' ] ) ) {
	$db = get_db_instance();
	$stmt = $db->prepare( 'SELECT null FROM mailbox WHERE username=? AND active = 1;' );
	$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
	if ( ! $user = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
		$_SESSION = [];
		session_regenerate_id( true );
		$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
		$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('It looks like your user no longer exists!')).'</div>';
	}
}

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
	if ( $_SESSION[ 'csrf_token' ] !== $_POST[ 'csrf_token' ] ?? '' ) {
		die( 'Invalid CSRF token' );
	}
	if ( isset( $_SESSION[ 'user_totp_pending' ] ) ) {
		$db = get_db_instance();
		$stmt = $db->prepare( 'SELECT totp_secret FROM mailbox WHERE username = ? AND active = 1;' );
		$stmt->execute( [ $_SESSION[ 'user_totp_pending' ] ] );
		$pending = $stmt->fetch( PDO::FETCH_ASSOC );
		if ( ! $pending || empty( $pending[ 'totp_secret' ] ) ) {
			unset( $_SESSION[ 'user_totp_pending' ] );
			$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Incorrect username or password')).'</div>';
		} elseif ( verify_totp_code( $pending[ 'totp_secret' ], $_POST[ 'totp_code' ] ?? '' ) ) {
			$_SESSION[ 'email_user' ] = $_SESSION[ 'user_totp_pending' ];
			unset( $_SESSION[ 'user_totp_pending' ] );
			session_regenerate_id( true );
			$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
			$stmt = $db->prepare( 'UPDATE mailbox SET last_login = ? WHERE username = ? AND active = 1;' );
			$stmt->execute( [ time(), $_SESSION[ 'email_user' ] ] );
		} else {
			$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Wrong 2FA code')).'</div>';
		}
	} elseif ( isset( $_SESSION[ '2fa_code' ] ) ) {
		if ( ! empty( $_POST[ '2fa_code' ] ) && $_POST[ '2fa_code' ] === $_SESSION[ '2fa_code' ] ) {
			unset( $_SESSION[ '2fa_code' ] );
			unset( $_SESSION[ 'pgp_key' ] );
		} else {
			$msg .= '<p style="color:red">'.htmlspecialchars(_('Wrong 2FA code')).'</p>';
		}
	}
	if ( ! isset( $_SESSION[ '2fa_code' ] ) && ! isset( $_SESSION[ 'user_totp_pending' ] ) && isset( $_POST[ 'action' ] ) ) {
		if ( $_POST[ 'action' ] === 'logout' ) {
			$_SESSION = [];
			session_regenerate_id( true );
			$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
			$msg .= '<div class="green" role="alert">'.htmlspecialchars(_('Successfully logged out')).'</div>';
		} elseif ( $_POST[ 'action' ] === 'login' ) {
			$ok = true;
			if ( ! check_captcha( $_POST[ 'challenge' ] ?? '', $_POST[ 'captcha' ] ?? '' ) ) {
				$ok = false;
				$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Invalid captcha')).'</div>';
			}
			if ( empty( $_POST[ 'user' ] ) || ! preg_match( '/^([^+]+?)(@([^@]+))?$/i', $_POST[ 'user' ], $match ) ) {
				$ok = false;
				$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Invalid username')).'</div>';
			}
			if ( $ok ) {
				$db = get_db_instance();
				$user = $match[ 1 ];
				$domain = $match[ 3 ] ?? CLEARNET_SERVER;
				$stmt = $db->prepare( 'SELECT target_domain FROM alias_domain WHERE alias_domain = ? AND active=1;' );
				$stmt->execute( [ $domain ] );
				if ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
					$domain = $tmp[ 'target_domain' ];
				}
				$stmt = $db->prepare( 'SELECT username, password, password_hash_type, tfa, pgp_key, totp_secret FROM mailbox WHERE username = ? AND active = 1;' );
				$stmt->execute( [ "$user@$domain" ] );
				if ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
					if ( empty( $_POST[ 'pwd' ] ) || ! password_verify( $_POST[ 'pwd' ], $tmp[ 'password' ] ) ) {
						$ok = false;
						$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Incorrect username or password')).'</div>';
					} else {
						// update password hash if it's using an old hashing algorithm
						if ( $tmp[ 'password_hash_type' ] !== '{ARGON2ID}' ) {
							$hash = password_hash( $_POST[ 'pwd' ], PASSWORD_ARGON2ID );
							$stmt = $db->prepare( 'UPDATE mailbox SET password_hash_type = "{ARGON2ID}", password = ? WHERE username = ? AND active = 1;' );
							$stmt->execute( [ $hash, $tmp[ 'username' ] ] );
						}
						if ( ! empty( $tmp[ 'totp_secret' ] ) ) {
							unset( $_SESSION[ 'email_user' ] );
							session_regenerate_id( true );
							$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
							$_SESSION[ 'user_totp_pending' ] = $tmp[ 'username' ];
						} else {
							$_SESSION[ 'email_user' ] = $tmp[ 'username' ];
							$stmt = $db->prepare( 'UPDATE mailbox SET last_login = ? WHERE username = ? AND active = 1;' );
							$stmt->execute( [ time(), $_SESSION[ 'email_user' ] ] );
							if ( $tmp[ 'tfa' ] ) {
								$code = bin2hex( random_bytes( 3 ) );
								$_SESSION[ '2fa_code' ] = $code;
								$_SESSION[ 'pgp_key' ] = $tmp[ 'pgp_key' ];
							}
						}
					}
				} else {
					$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Incorrect username or password')).'</div>';
				}
			}
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'update_settings' ) {
			$alias_goto = '';
			if ( isset( $_POST[ 'alias_keep_copy' ] ) ) {
				$alias_goto .= $_SESSION[ 'email_user' ] . ',';
			}
			if ( ! empty( $_POST[ 'alias_to' ] ) ) {
				$additional = preg_split( "/[\s,]+/", $_POST[ 'alias_to' ] );
				$alias_goto .= validate_email_list( $additional, $msg );
			}
			$alias_goto = rtrim( $alias_goto, ',' );
			$stmt = $db->prepare( 'UPDATE alias SET goto = ?, enforce_tls_in = ? WHERE address = ? AND active = 1;' );
			$stmt->execute( [ $alias_goto, ( isset( $_POST[ 'enforce_tls_in' ] ) ? 1 : 0 ), $_SESSION[ 'email_user' ] ] );
			$stmt = $db->prepare( 'UPDATE mailbox SET enforce_tls_in = ?, enforce_tls_out = ? WHERE username = ? AND active = 1;' );
			$stmt->execute( [ ( isset( $_POST[ 'enforce_tls_in' ] ) ? 1 : 0 ), ( isset( $_POST[ 'enforce_tls_out' ] ) ? 1 : 0 ), $_SESSION[ 'email_user' ] ] );
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'add_identity' ) {
			$identityAddress = $_POST[ 'identity_address' ] ?? '';
			if ( ! is_string( $identityAddress ) ) {
				$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Invalid email address.')).'</div>';
			} elseif ( create_mailbox_identity( $db, $identityAddress, $_SESSION[ 'email_user' ], $msg ) ) {
				$msg .= '<div class="green" role="status">'.htmlspecialchars(_('Successfully added identity address.')).'</div>';
			}
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'delete_identity' && is_string( $_POST[ 'identity_address' ] ?? null ) && $_POST[ 'identity_address' ] !== '' ) {
			$stmt = $db->prepare( 'DELETE FROM alias WHERE address = ? AND identity = ?;' );
			$stmt->execute( [ $_POST[ 'identity_address' ], $_SESSION[ 'email_user' ] ] );
			$msg .= '<div class="green" role="status">'.htmlspecialchars(_('Successfully removed identity address.')).'</div>';
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'update_inactivity_period' ) {
			$inactivity_period = filter_var( $_POST[ 'inactivity_period' ] ?? null, FILTER_VALIDATE_INT );
			$valid_inactivity_period = $inactivity_period !== false && (
				in_array( $inactivity_period, INACTIVITY_PERIOD_MONTHS, true )
				|| ( ALLOW_ACCOUNT_NEVER_DELETE && $inactivity_period === 0 )
			);
			if ( ! $valid_inactivity_period ) {
				$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Please select a valid inactivity period.')).'</div>';
			} else {
				$stmt = $db->prepare( 'UPDATE mailbox SET inactivity_period = ? WHERE username = ? AND active = 1;' );
				$stmt->execute( [ $inactivity_period, $_SESSION[ 'email_user' ] ] );
				$msg .= '<div class="green" role="status">'.htmlspecialchars(_('Successfully updated the inactivity period.')).'</div>';
			}
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'update_spam_settings' ) {
			$spam_reject_score = filter_var( $_POST[ 'spam_reject_score' ] ?? null, FILTER_VALIDATE_INT );
			if ( ! in_array( $spam_reject_score, SPAM_REJECT_SCORE_OPTIONS, true ) ) {
				$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Please select a valid spam reject score.')).'</div>';
			} else {
				$stmt = $db->prepare( 'UPDATE mailbox SET spam_reject_score = ? WHERE username = ? AND active = 1;' );
				$stmt->execute( [ $spam_reject_score, $_SESSION[ 'email_user' ] ] );
				$msg .= '<div class="green" role="status">'.htmlspecialchars(_('Successfully updated spam filter settings, changes can take up to 10 minutes to take effect.')).'</div>';
			}
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'update_sender_rules' ) {
			$blocked_senders = is_string( $_POST[ 'blocked_senders' ] ?? null ) ? parse_sender_address_list( $_POST[ 'blocked_senders' ] ) : null;
			$whitelisted_senders = is_string( $_POST[ 'whitelisted_senders' ] ?? null ) ? parse_sender_address_list( $_POST[ 'whitelisted_senders' ] ) : null;
			if ( $blocked_senders === null || $whitelisted_senders === null ) {
				$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Please enter a valid email address on each line.')).'</div>';
			} elseif ( array_intersect( $blocked_senders, $whitelisted_senders ) ) {
				$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('An email address cannot be both blocked and whitelisted.')).'</div>';
			} else {
				$stmt = $db->prepare( 'UPDATE mailbox SET blocked_senders = ?, whitelisted_senders = ? WHERE username = ? AND active = 1;' );
				$stmt->execute( [ implode( "\n", $blocked_senders ), implode( "\n", $whitelisted_senders ), $_SESSION[ 'email_user' ] ] );
				$msg .= '<div class="green" role="status">'.htmlspecialchars(_('Successfully updated sender rules. Changes can take up to 10 minutes to take effect.')).'</div>';
			}
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'update_password' ) {
			if ( empty( $_POST[ 'pass_update' ] ) || empty( $_POST[ 'pass_update2' ] ) || $_POST[ 'pass_update' ] !== $_POST[ 'pass_update2' ] ) {
				$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Passwords empty or don\'t match')).'</div>';
			} else {
				$hash = password_hash( $_POST[ 'pass_update' ], PASSWORD_ARGON2ID );
				$stmt = $db->prepare( 'UPDATE mailbox SET password_hash_type = "{ARGON2ID}", password = ? WHERE username = ? AND active = 1;' );
				$stmt->execute( [ $hash, $_SESSION[ 'email_user' ] ] );
				$msg .= '<div class="green" role="alert">'.htmlspecialchars(_('Successfully updated password')).'</div>';
			}
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'delete_account' ) {
			$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Warning: This will permenently delete your account and all your data. Anyone can immediately register with this user again. It cannot be reversed. Are you absolutely sure?')).'</div>';
			$msg .= '<form method="post"><input type="hidden" name="csrf_token" value="' . $_SESSION[ 'csrf_token' ] . '">';
			$msg .= '<button type="submit" name="action" value="delete_account2">'.htmlspecialchars(_('Yes, I want to permanently delete my account')).'</button></form>';
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'disable_account' ) {
			$stmt = $db->prepare( 'SELECT inactivity_period FROM mailbox WHERE username = ? AND active = 1;' );
			$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
			$inactivity_period = (int) $stmt->fetchColumn();
			$disable_warning = ALLOW_ACCOUNT_NEVER_DELETE && $inactivity_period === 0
				? _('Warning: This will disable your account and delete all your data. The address will not become available for registration again. It cannot be reversed. Are you absolutely sure?')
				: _('Warning: This will disable your account and delete all your data. It will be available for registrations again after the selected inactivity period without a login. It cannot be reversed. Are you absolutely sure?');
			$msg .= '<div class="red" role="alert">'.htmlspecialchars($disable_warning).'</div>';
			$msg .= '<form method="post"><input type="hidden" name="csrf_token" value="' . $_SESSION[ 'csrf_token' ] . '">';
			$msg .= '<button type="submit" name="action" value="disable_account2">'.htmlspecialchars(_('Yes, I want to disable my account')).'</button></form>';
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'delete_account2' ) {
			$stmt = $db->prepare( 'DELETE FROM alias WHERE address = ? OR identity = ?;' );
			$stmt->execute( [ $_SESSION[ 'email_user' ], $_SESSION[ 'email_user' ] ] );
			$stmt = $db->prepare( 'UPDATE mailbox SET active = -2 WHERE username = ? AND active = 1;' );
			$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
			$_SESSION = [];
			session_regenerate_id( true );
			$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
			$msg .= '<div class="green" role="alert">'.htmlspecialchars(_('Successfully deleted account')).'</div>';
		} elseif ( ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'disable_account2' ) {
			$stmt = $db->prepare( 'UPDATE alias SET active = 0 WHERE address = ?;' );
			$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
			$stmt = $db->prepare( 'UPDATE alias SET active = 0 WHERE identity = ?;' );
			$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
			$stmt = $db->prepare( 'UPDATE mailbox SET active = -1 WHERE username = ? AND active = 1;' );
			$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
			$_SESSION = [];
			session_regenerate_id( true );
			$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
			$msg .= '<div class="green" role="alert">'.htmlspecialchars(_('Successfully disabled account')).'</div>';
		} elseif ( isset( $_POST[ 'pgp_key' ] ) && ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'update_pgp_key' ) {
			$pgp_key = trim( $_POST[ 'pgp_key' ] );
			if ( empty( $pgp_key ) ) {
				$msg .= '<p class="green">'.htmlspecialchars(_('Successfully removed the key')).'</p>';
				$stmt = $db->prepare( 'UPDATE mailbox SET pgp_key = "", tfa = 0, pgp_verified = 0 WHERE username = ?;' );
				$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
			} else {
				$gpg = gnupg_init();
				gnupg_seterrormode( $gpg, GNUPG_ERROR_WARNING );
				gnupg_setarmor( $gpg, 1 );
				$imported_key = gnupg_import( $gpg, $pgp_key );
				if ( ! $imported_key ) {
					$msg .= '<p class="red">'.htmlspecialchars(_('There was an error importing the key')).'</p>';
				} else {
					$has_this_email = false;
					$key_info = gnupg_keyinfo( $gpg, $imported_key[ 'fingerprint' ] );
					foreach ( $key_info as $key ) {
						foreach ( $key[ 'uids' ] as $uid ) {
							if ( $uid[ 'email' ] === $_SESSION[ 'email_user' ] ) {
								$has_this_email = true;
								break;
							}
						}
					}
					if ( $has_this_email ) {
						$msg .= '<p class="green">'.htmlspecialchars(_('Successfully imported the key')).'</p>';
						$stmt = $db->prepare( 'UPDATE mailbox SET pgp_key = ?, tfa = 0, pgp_verified = 0 WHERE username = ?;' );
						$stmt->execute( [ $pgp_key, $_SESSION[ 'email_user' ] ] );
					} else {
						$msg .= '<p class="red">' . sprintf( htmlspecialchars(_('Oops, looks like the key is missing this email address as user id. Please add your address "%s" as user ID to your pgp key or create a new key pair.')), htmlspecialchars( $_SESSION[ 'email_user' ] ) ) . '</p>';
					}
				}
			}
		} elseif ( isset( $_POST[ 'enable_2fa_code' ] ) && ! empty( $_SESSION[ 'email_user' ] ) && $_POST[ 'action' ] === 'enable_2fa' ) {
			if ( $_POST[ 'enable_2fa_code' ] !== $_SESSION[ 'enable_2fa_code' ] ) {
				$msg .= '<p class="red">'.htmlspecialchars(_('Sorry, the code was incorrect')).'</p>';
			} else {
				$stmt = $db->prepare( 'UPDATE mailbox SET tfa = 1, pgp_verified = 1 WHERE username = ?;' );
				$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
				$msg .= '<p class="green">'.htmlspecialchars(_('Successfully enabled 2FA')).'</p>';
			}
		} elseif ( $_POST[ 'action' ] === 'disable_pgp_2fa' && ! empty( $_SESSION[ 'email_user' ] ) ) {
			$stmt = $db->prepare( 'UPDATE mailbox SET tfa = 0 WHERE username = ? AND active = 1;' );
			$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
			$msg .= '<p class="green">'.htmlspecialchars(_('Successfully disabled two-factor authentication')).'</p>';
		} elseif ( $_POST[ 'action' ] === 'start_totp_setup' && ! empty( $_SESSION[ 'email_user' ] ) ) {
			$_SESSION[ 'user_totp_setup' ] = generate_totp_secret();
		} elseif ( $_POST[ 'action' ] === 'enable_totp' && ! empty( $_SESSION[ 'email_user' ] ) && ! empty( $_SESSION[ 'user_totp_setup' ] ) ) {
			if ( verify_totp_code( $_SESSION[ 'user_totp_setup' ], $_POST[ 'totp_code' ] ?? '' ) ) {
				$stmt = $db->prepare( 'UPDATE mailbox SET totp_secret = ? WHERE username = ? AND active = 1;' );
				$stmt->execute( [ $_SESSION[ 'user_totp_setup' ], $_SESSION[ 'email_user' ] ] );
				unset( $_SESSION[ 'user_totp_setup' ] );
				$msg .= '<p class="green">'.htmlspecialchars(_('Successfully enabled TOTP two-factor authentication')).'</p>';
			} else {
				$msg .= '<p class="red">'.htmlspecialchars(_('Invalid TOTP code')).'</p>';
			}
		} elseif ( $_POST[ 'action' ] === 'disable_totp' && ! empty( $_SESSION[ 'email_user' ] ) ) {
			$stmt = $db->prepare( 'SELECT totp_secret FROM mailbox WHERE username = ? AND active = 1;' );
			$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
			$totp_secret = $stmt->fetchColumn();
			if ( is_string( $totp_secret ) && verify_totp_code( $totp_secret, $_POST[ 'totp_code' ] ?? '' ) ) {
				$stmt = $db->prepare( 'UPDATE mailbox SET totp_secret = NULL WHERE username = ? AND active = 1;' );
				$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
				unset( $_SESSION[ 'user_totp_setup' ] );
				$msg .= '<p class="green">'.htmlspecialchars(_('Successfully disabled TOTP two-factor authentication')).'</p>';
			} else {
				$msg .= '<p class="red">'.htmlspecialchars(_('Invalid TOTP code')).'</p>';
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>" dir="<?php echo $dir; ?>">
<head>
    <title><?php echo htmlspecialchars(_('E-Mail and XMPP - Manage account')); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="author" content="Daniel Winzen">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
          content="<?php echo htmlspecialchars(_('Manage your free and anonymous E-Mail address and an XMPP/Jabber account. Add forwarding addresses, change your password or disable/delete your account.')); ?>">
    <link rel="canonical" href="<?php echo CANONICAL_URL; ?>manage_account.php">
    <link rel="alternate" href="<?php echo CANONICAL_URL; ?>manage_account.php" hreflang="x-default">
	<?php alt_links(); ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars(_('E-Mail and XMPP - Manage account')); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(_('Manage your free and anonymous E-Mail address and an XMPP/Jabber account. Add forwarding addresses, change your password or disable/delete your account.')); ?>">
    <meta property="og:url" content="<?php echo CANONICAL_URL; ?>manage_account.php">
    <meta property="og:locale" content="<?php echo $locale; ?>">
    <script type="application/ld+json">{"@context":"https://schema.org","@type":"WebPage","name":"<?php echo htmlspecialchars(_('E-Mail and XMPP - Manage account')); ?>", "description": "<?php echo htmlspecialchars(_('Manage your free and anonymous E-Mail address and an XMPP/Jabber account. Add forwarding addresses, change your password or disable/delete your account.')); ?>"}</script>
</head>
<body>
<main>
<?php
if ( isset( $_SESSION[ 'user_totp_pending' ] ) ) {
	echo $msg;
	?>
    <p><?php echo htmlspecialchars(_('Enter the current code from your authenticator app to finish logging in.')); ?></p>
    <form class="form_limit" action="manage_account.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="totp_code"><?php echo htmlspecialchars(_('Authentication code')); ?></label></div>
            <div class="col"><input type="text" name="totp_code" id="totp_code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required></div>
        </div>
        <button type="submit"><?php echo htmlspecialchars(_('Confirm')); ?></button>
    </form>
</main></body>
</html>
	<?php
	exit;
} elseif ( isset( $_SESSION[ '2fa_code' ] ) ){
	$gpg = gnupg_init();
	gnupg_seterrormode( $gpg, GNUPG_ERROR_WARNING );
	gnupg_setarmor( $gpg, 1 );
	$imported_key = gnupg_import( $gpg, $_SESSION[ 'pgp_key' ] );
	$key_valid = false;
	if ( $imported_key ){
		$key_info = gnupg_keyinfo( $gpg, $imported_key[ 'fingerprint' ] );
		foreach ( is_array( $key_info ) ? $key_info : [] as $key ) {
			if ( $key[ 'can_encrypt' ] && ! $key['expired'] && ! $key['revoked'] ) {
				foreach ( $key[ 'subkeys' ] as $subkey ) {
					if( $subkey[ 'can_encrypt' ] && ! $subkey['expired'] && ! $subkey['revoked'] ) {
						if(gnupg_addencryptkey( $gpg, $subkey[ 'fingerprint' ] )){
							$key_valid = true;
						}
					}
				}
			}
		}
		$encrypted = gnupg_encrypt( $gpg, _('To login, please enter the following code to confirm ownership of your key:')."\n\n" . $_SESSION[ '2fa_code' ] . "\n" );
	}
	if ( ! $key_valid || ! $encrypted ) {
		unset( $_SESSION[ '2fa_code' ] );
		unset( $_SESSION[ 'pgp_key' ] );
		$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Your PGP key has expired, so PGP two-factor authentication was skipped for this login. Update your PGP key to restore PGP two-factor authentication.')).'</div>';
	} else {
		echo $msg;
		echo '<p>'.htmlspecialchars(_('To login, please decrypt the following PGP encrypted message and confirm the code:')).'</p>';
		echo '<pre>'.htmlspecialchars( $encrypted ).'</pre>';
		?>
		<?php if ( $encrypted ) { ?>
			<form class="form_limit" action="manage_account.php" method="post">
				<input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
				<div class="row">
					<div class="col"><input type="text" name="2fa_code" aria-label="<?php echo htmlspecialchars(_('2FA code')); ?>"></div>
					<div class="col">
						<button type="submit"><?php echo htmlspecialchars(_('Confirm')); ?></button>
					</div>
				</div>
			</form>
		<?php } ?>
		</main></body>
		</html>
		<?php
		exit;
	}
}
if ( ! empty( $_SESSION[ 'email_user' ] ) ){ ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
	<?php } ?>
    <p><a href="<?php echo ROOT_URL; ?>"><?php echo htmlspecialchars(_('Info')); ?></a> |<?php
		if ( ! empty( $_SESSION[ 'email_user' ] ) ) {
            printf(htmlspecialchars(_('Logged in as %s')), htmlspecialchars( $_SESSION[ 'email_user' ] ) );
		} else { ?>
            <a href="<?php echo ROOT_URL; ?>register.php"><?php echo htmlspecialchars(_('Register')); ?></a>
		<?php }
		if ( ! empty( $_SESSION[ 'email_user' ] ) ) { ?>
            |
            <button name="action" value="logout" type="submit"><?php echo htmlspecialchars(_('Logout')); ?></button>
		<?php } else { ?>
            | <?php echo htmlspecialchars(_('Manage account'));
		} ?> | <a href="<?php echo ROOT_URL; ?>squirrelmail/src/login.php" target="_blank"><?php echo htmlspecialchars(_('SquirrelMail')); ?></a> | <a href="<?php echo ROOT_URL; ?>snappymail/" target="_blank"><?php echo htmlspecialchars(_('SnappyMail')); ?></a> | <a href="<?php echo WEB_XMPP_URL; ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(_('Web-XMPP')); ?></a></p>
	<?php if ( ! empty( $_SESSION[ 'email_user' ] ) ){ ?></form><?php }
echo "<p>$msg</p>";
if ( empty( $_SESSION[ 'email_user' ] ) ) { ?>
    <form class="form_limit" action="manage_account.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="user"><?php echo htmlspecialchars(_('Username')); ?></label></div>
            <div class="col"><input type="text" name="user" id="user" autocomplete="username" required
                                    value="<?php echo htmlspecialchars( $_POST[ 'user' ] ?? '' ); ?>"></div>
        </div>
        <div class="row">
            <div class="col"><label for="pwd"><?php echo htmlspecialchars(_('Password')); ?></label></div>
            <div class="col"><input type="password" name="pwd" id="pwd" autocomplete="new-password" required></div>
        </div>
		<?php send_captcha(); ?>
        <div class="row">
            <div class="col">
                <button name="action" value="login" type="submit"><?php echo htmlspecialchars(_('Login')); ?></button>
            </div>
        </div>
    </form>
<?php } else {
	$aliases = [];
	$stmt = $db->prepare( 'SELECT goto FROM alias WHERE address = ?;' );
	$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
	if ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
		$aliases = explode( ',', $tmp[ 'goto' ] );
	}
	$aliases_to = implode( "\n", array_diff( $aliases, [ $_SESSION[ 'email_user' ] ] ) );
	$stmt = $db->prepare( 'SELECT enforce_tls_in, enforce_tls_out, inactivity_period, spam_reject_score, blocked_senders, whitelisted_senders FROM mailbox WHERE username = ?;' );
	$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
	$mailbox_options = $stmt->fetch( PDO::FETCH_ASSOC );
	?>
    <form class="form_limit" action="manage_account.php" method="post">
        <h2><?php echo htmlspecialchars(_('Settings')); ?></h2>
        <h3><?php echo htmlspecialchars(_('Delivery')); ?></h3>
        <p><?php echo htmlspecialchars(_('Change how your mail is delivered. You can add forwarding addresses one per line, or comma seperated. When you disable the "keep a local copy" checkbox, your mail will only be sent to your forwarding addresses.')); ?></p>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="alias_to"><?php echo htmlspecialchars(_('Forward to')); ?></label></div>
            <div class="col"><textarea name="alias_to"
                                       id="alias_to"><?php echo htmlspecialchars( $aliases_to ); ?></textarea></div>
        </div>
        <div class="row">
            <div class="col"><label for="alias_keep_copy"><?php echo htmlspecialchars(_('Keep a local copy')); ?></label></div>
            <div class="col"><input type="checkbox" name="alias_keep_copy"
                                    id="alias_keep_copy"<?php echo in_array( $_SESSION[ 'email_user' ], $aliases, true ) ? ' checked' : ''; ?>>
            </div>
        </div>
        <h3><?php echo htmlspecialchars(_('Encryption')); ?></h3>
        <p><?php echo htmlspecialchars(_('If you are having issues sending or receiving mails with some other provider, you can try disabling forced encryption here. But be aware, that this makes it possible for 3rd parties on the network to read your emails. Make sure to ask your correspondent to demand encryption support from their provider for a safer internet.')); ?></p>
        <div class="row">
            <div class="col"><label for="enforce_tls_in"><?php echo htmlspecialchars(_('Enforce encryption for incoming mail')); ?></label></div>
            <div class="col"><input type="checkbox" name="enforce_tls_in"
                                    id="enforce_tls_in"<?php echo ! empty( $mailbox_options[ 'enforce_tls_in' ] ) ? ' checked' : ''; ?>>
            </div>
        </div>
        <div class="row">
            <div class="col"><label for="enforce_tls_out"><?php echo htmlspecialchars(_('Enforce encryption for outgoing mail')); ?></label></div>
            <div class="col"><input type="checkbox" name="enforce_tls_out"
                                    id="enforce_tls_out"<?php echo ! empty( $mailbox_options[ 'enforce_tls_out' ] ) ? ' checked' : ''; ?>>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <button name="action" value="update_settings" type="submit"><?php echo htmlspecialchars(_('Update settings')); ?></button>
            </div>
        </div>
    </form>

    <h2><?php echo htmlspecialchars(_('Identity addresses')); ?></h2>
    <p><?php echo htmlspecialchars(_('Identity addresses receive mail in this mailbox and can be used as a sender for this account. They must use your mailbox domain.')); ?></p>
	<?php
	$identityStmt = $db->prepare( 'SELECT address FROM alias WHERE identity = ? ORDER BY address;' );
	$identityStmt->execute( [ $_SESSION[ 'email_user' ] ] );
	while ( $identity = $identityStmt->fetch( PDO::FETCH_ASSOC ) ) {
		?>
        <form class="form_limit" action="manage_account.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <input type="hidden" name="identity_address" value="<?php echo htmlspecialchars( $identity[ 'address' ] ); ?>">
            <span><?php echo htmlspecialchars( $identity[ 'address' ] ); ?></span>
            <button name="action" value="delete_identity" type="submit"><?php echo htmlspecialchars(_('Remove identity')); ?></button>
        </form>
		<?php
	}
	?>
    <form class="form_limit" action="manage_account.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="identity_address"><?php echo htmlspecialchars(_('New identity address')); ?></label></div>
            <div class="col"><input type="email" name="identity_address" id="identity_address" required></div>
        </div>
        <button name="action" value="add_identity" type="submit"><?php echo htmlspecialchars(_('Add identity')); ?></button>
    </form>

    <form class="form_limit" action="manage_account.php" method="post">
        <h3><?php echo htmlspecialchars(_('Spam filtering')); ?></h3>
        <p><?php echo htmlspecialchars(_('Disabling spam filtering by setting the score to 0 bypasses all spam checks for incoming mail. The higher the score, the more spam you may receive.')); ?></p>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="spam_reject_score"><?php echo htmlspecialchars(_('Spam reject score for incoming mail')); ?></label></div>
            <div class="col">
                <select name="spam_reject_score" id="spam_reject_score">
					<?php foreach ( SPAM_REJECT_SCORE_OPTIONS as $score ) { ?>
                        <option value="<?php echo $score; ?>"<?php echo (int) $mailbox_options[ 'spam_reject_score' ] === $score ? ' selected' : ''; ?>><?php echo $score === 0 ? htmlspecialchars(_('Disabled')) : sprintf(htmlspecialchars(_('%d points')), $score); ?></option>
					<?php } ?>
                </select>
            </div>
        </div>
        <button name="action" value="update_spam_settings" type="submit"><?php echo htmlspecialchars(_('Update spam filter settings')); ?></button>
    </form>

    <form class="form_limit" action="manage_account.php" method="post">
        <h3><?php echo htmlspecialchars(_('Account inactivity')); ?></h3>
        <p><?php echo htmlspecialchars(_('Choose how long your account can remain inactive before it and its data are automatically deleted. The period is measured from your last login, or from account creation if you have never logged in.')); ?></p>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="inactivity_period"><?php echo htmlspecialchars(_('Delete after inactivity')); ?></label></div>
            <div class="col">
                <select name="inactivity_period" id="inactivity_period">
					<?php if ( ALLOW_ACCOUNT_NEVER_DELETE ) { ?>
                        <option value="0"<?php echo (int) $mailbox_options[ 'inactivity_period' ] === 0 ? ' selected' : ''; ?>><?php echo htmlspecialchars(_('Never')); ?></option>
					<?php } ?>
					<?php foreach ( get_inactivity_period_options() as $months => $label ) { ?>
                        <option value="<?php echo $months; ?>"<?php echo (int) $mailbox_options[ 'inactivity_period' ] === $months ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
					<?php } ?>
                </select>
            </div>
        </div>
        <button name="action" value="update_inactivity_period" type="submit"><?php echo htmlspecialchars(_('Update inactivity period')); ?></button>
    </form>

    <form class="form_limit" action="manage_account.php" method="post">
        <h3><?php echo htmlspecialchars(_('Sender rules')); ?></h3>
        <p><?php echo htmlspecialchars(_('Enter one exact sender email address per line. Blocking a sender rejects matching incoming mail. Whitelisting a sender bypasses all spam checks, for matching mail.')); ?></p>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="blocked_senders"><?php echo htmlspecialchars(_('Blocked senders')); ?></label></div>
            <div class="col"><textarea name="blocked_senders" id="blocked_senders"><?php echo htmlspecialchars( $mailbox_options[ 'blocked_senders' ] ); ?></textarea></div>
        </div>
        <div class="row">
            <div class="col"><label for="whitelisted_senders"><?php echo htmlspecialchars(_('Whitelisted senders')); ?></label></div>
            <div class="col"><textarea name="whitelisted_senders" id="whitelisted_senders"><?php echo htmlspecialchars( $mailbox_options[ 'whitelisted_senders' ] ); ?></textarea></div>
        </div>
        <button name="action" value="update_sender_rules" type="submit"><?php echo htmlspecialchars(_('Update sender rules')); ?></button>
    </form>

    <h2><?php echo htmlspecialchars(_('Change password')); ?></h2>
    <form class="form_limit" action="manage_account.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="pass_update"><?php echo htmlspecialchars(_('Password')); ?></label></div>
            <div class="col"><input type="password" name="pass_update" id="pass_update" autocomplete="new-password"
                                    required></div>
        </div>
        <div class="row">
            <div class="col"><label for="pass_update2"><?php echo htmlspecialchars(_('Password again')); ?></label></div>
            <div class="col"><input type="password" name="pass_update2" id="pass_update2" autocomplete="new-password"
                                    required></div>
        </div>
        <div class="row">
            <div class="col">
                <button name="action" value="update_password" type="submit"><?php echo htmlspecialchars(_('Change password')); ?></button>
            </div>
        </div>
    </form>

	<?php
	$stmt = $db->prepare( 'SELECT pgp_key, tfa, totp_secret FROM mailbox WHERE username = ?;' );
	$stmt->execute( [ $_SESSION[ 'email_user' ] ] );
	$pgp_status = $stmt->fetch( PDO::FETCH_ASSOC );
	?>
    <h2><?php echo htmlspecialchars(_('Authenticator app (TOTP) two-factor authentication')); ?></h2>
	<?php if ( ! empty( $pgp_status[ 'totp_secret' ] ) ) { ?>
        <p class="green"><?php echo htmlspecialchars(_('TOTP two-factor authentication is enabled. It is used instead of PGP for login while enabled.')); ?></p>
        <form class="form_limit" action="manage_account.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <div class="row">
                <div class="col"><label for="disable_totp_code"><?php echo htmlspecialchars(_('Current authentication code')); ?></label></div>
                <div class="col"><input type="text" name="totp_code" id="disable_totp_code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required></div>
            </div>
            <button type="submit" name="action" value="disable_totp"><?php echo htmlspecialchars(_('Disable TOTP')); ?></button>
        </form>
	<?php } elseif ( ! empty( $_SESSION[ 'user_totp_setup' ] ) ) {
		$totp_uri = get_totp_provisioning_uri( $_SESSION[ 'email_user' ], $_SESSION[ 'user_totp_setup' ] );
		$totp_qr = get_totp_qr_data_uri( $totp_uri );
		?>
        <p><?php echo htmlspecialchars(_('Add this secret to your authenticator app, then enter the generated code to enable TOTP.')); ?></p>
        <p><img src="<?php echo htmlspecialchars( $totp_qr ); ?>" alt="<?php echo htmlspecialchars(_('TOTP setup QR code')); ?>" width="220" height="220"></p>
        <p><strong><?php echo htmlspecialchars($_SESSION[ 'user_totp_setup' ]); ?></strong></p>
        <form class="form_limit" action="manage_account.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <div class="row">
                <div class="col"><label for="enable_totp_code"><?php echo htmlspecialchars(_('Authentication code')); ?></label></div>
                <div class="col"><input type="text" name="totp_code" id="enable_totp_code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required></div>
            </div>
            <button type="submit" name="action" value="enable_totp"><?php echo htmlspecialchars(_('Enable TOTP')); ?></button>
        </form>
	<?php } else { ?>
        <form class="form_limit" action="manage_account.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <button type="submit" name="action" value="start_totp_setup"><?php echo htmlspecialchars(_('Set up TOTP')); ?></button>
        </form>
	<?php }
	if ( empty( $pgp_status[ 'totp_secret' ] ) && ! empty( $pgp_status[ 'pgp_key' ] ) ) {
		if ( (int) $pgp_status[ 'tfa' ] === 1 ) {
			?>
            <form class="form_limit" action="manage_account.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
                <button type="submit" name="action" value="disable_pgp_2fa"><?php echo htmlspecialchars(_('Disable two-factor authentication')); ?></button>
            </form>
			<?php
		} else {
			$gpg = gnupg_init();
			gnupg_seterrormode( $gpg, GNUPG_ERROR_WARNING );
			gnupg_setarmor( $gpg, 1 );
			$imported_key = gnupg_import( $gpg, $pgp_status[ 'pgp_key' ] );
			if ( $imported_key ) {
				$key_info = gnupg_keyinfo( $gpg, $imported_key[ 'fingerprint' ] );
				foreach ( $key_info as $key ) {
					if ( ! $key[ 'can_encrypt' ] ) {
						echo '<p>'.htmlspecialchars(_('Sorry, this key can\'t be used to encrypt a message to you. Your key may have expired or has been revoked.')).'</p>';
					} else {
						foreach ( $key[ 'subkeys' ] as $subkey ) {
							gnupg_addencryptkey( $gpg, $subkey[ 'fingerprint' ] );
						}
					}
				}
				$_SESSION[ 'enable_2fa_code' ] = bin2hex( random_bytes( 3 ) );
				if ( $encrypted = gnupg_encrypt( $gpg, _('To enable 2FA, please enter the following code to confirm ownership of your key:'). "\n\n$_SESSION[enable_2fa_code]\n" ) ) {
					echo '<h2>'.htmlspecialchars(_( 'Enable 2FA')).'</h2>';
					echo '<p>'.htmlspecialchars(_('To enable 2FA using your PGP key, please decrypt the following PGP encrypted message and confirm the code:')).'</p>';
					echo "<pre>$encrypted</pre>";
					?>
                    <form class="form_limit" action="manage_account.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
                        <div class="row">
                            <div class="col"><input type="text" name="enable_2fa_code" aria-label="<?php echo htmlspecialchars(_('2FA code')); ?>"></div>
                            <div>
                                <button type="submit" name="action" value="enable_2fa"><?php echo htmlspecialchars(_('Confirm')); ?></button>
                            </div>
                        </div>
                    </form>
					<?php
				}
			}
		}
	}
	?>

    <h2><?php echo htmlspecialchars(_('Add PGP key for 2FA and end-to-end encryption')); ?></h2>
    <form class="form_limit" action="manage_account.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><textarea name="pgp_key" rows="10" cols="50"
                                       aria-label="<?php echo htmlspecialchars(_('PGP key')); ?>"><?php echo htmlspecialchars( $pgp_status[ 'pgp_key' ] ?? '' ); ?></textarea>
            </div>
        </div>
        <div>
            <div>
                <button type="submit" name="action" value="update_pgp_key"><?php echo htmlspecialchars(_('Update PGP key')); ?></button>
            </div>
        </div>
    </form>

    <form class="form_limit" action="manage_account.php" method="post">
        <h2><?php echo htmlspecialchars(_('Disable/Delete account')); ?></h2>
        <p><?php echo htmlspecialchars(_('Warning, this is permanent and cannot be undone. Disabling an account will delete your email data from the server, but leave the account blocked in the database   for a year, so no one else can use it. Deleting your account will completely wipe all records of it and it will be available for new registrations again.')); ?></p>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col">
                <button type="submit" name="action" value="disable_account"><?php echo htmlspecialchars(_('Disable account')); ?></button>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <button type="submit" name="action" value="delete_account"><?php echo htmlspecialchars(_('Delete account')); ?></button>
            </div>
        </div>
    </form>
<?php } ?>
</main>
</body></html>
