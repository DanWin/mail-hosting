<?php

use Egulias\EmailValidator\EmailLexer;
use Egulias\EmailValidator\EmailParser;

require_once( '../common_config.php' );

session_start();
if ( empty( $_SESSION[ 'csrf_token' ] ) ) {
	$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
}
$msg = '';
$db = get_db_instance();
if ( ! empty( $_SESSION[ 'email_admin_user' ] ) ) {
	$stmt = $db->prepare( 'SELECT null FROM admin WHERE username=? AND active = 1;' );
	$stmt->execute( [ $_SESSION[ 'email_admin_user' ] ] );
	if ( ! $stmt->fetch( PDO::FETCH_ASSOC ) ) {
		$_SESSION = [];
		session_regenerate_id( true );
		$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
		$msg .= '<div class="red" role="alert">It looks like your user no longer exists!</div>';
	}
}
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
	if ( isset( $_POST[ 'action' ] ) ) {
		if ( $_SESSION[ 'csrf_token' ] !== $_POST[ 'csrf_token' ] ?? '' ) {
			die( 'Invalid csfr token' );
		}
		if ( $_POST[ 'action' ] === 'logout' ) {
			$_SESSION = [];
			session_regenerate_id( true );
			$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
			$msg .= '<div class="green" role="alert">Successfully logged out</div>';
		} elseif ( $_POST[ 'action' ] === 'login' ) {
			if ( empty( $_POST[ 'user' ] ) ) {
				$ok = false;
				$msg .= '<div class="red" role="alert">Invalid username.</div>';
			}
			$stmt = $db->prepare( 'SELECT username, password, password_hash_type, superadmin FROM admin WHERE username = ? AND active = 1;' );
			$stmt->execute( [ $_POST[ 'user' ] ] );
			if ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
				if ( empty( $_POST[ 'pwd' ] ) || ! password_verify( $_POST[ 'pwd' ], $tmp[ 'password' ] ) ) {
					$msg .= '<div class="red" role="alert">Incorrect username or password</div>';
				} else {
					$_SESSION[ 'email_admin_user' ] = $tmp[ 'username' ];
					$_SESSION[ 'email_admin_superadmin' ] = (bool) $tmp[ 'superadmin' ];
					// update password hash if it's using an old hashing algorithm
					if ( $tmp[ 'password_hash_type' ] !== '{ARGON2ID}' ) {
						$hash = password_hash( $_POST[ 'pwd' ], PASSWORD_ARGON2ID );
						$stmt = $db->prepare( 'UPDATE admin SET password_hash_type = "{ARGON2ID}", password = ? WHERE username = ? AND active = 1;' );
						$stmt->execute( [ $hash, $_SESSION[ 'email_admin_user' ] ] );
					}
				}
			} else {
				$msg .= '<div class="red" role="alert">Incorrect username or password.</div>';
			}
		} elseif ( ! empty( $_SESSION[ 'email_admin_user' ] ) ) {
			if ( $_POST[ 'action' ] === 'update_alias' ) {
				$alias_goto = '';
				if ( isset( $_POST[ 'alias_keep_copy' ] ) ) {
					$alias_goto .= $_SESSION[ 'email_admin_user' ] . ',';
				}
				if ( ! empty( $_POST[ 'alias_to' ] ) ) {
					$additional = preg_split( "/[\s,]+/", $_POST[ 'alias_to' ] );
					$alias_goto .= validate_email_list( $additional, $msg );
				}
				$alias_goto = rtrim( $alias_goto, ',' );
				$stmt = $db->prepare( 'UPDATE alias SET goto = ? WHERE address = ? AND active = 1;' );
				$stmt->execute( [ $alias_goto, $_SESSION[ 'email_admin_user' ] ] );

			} elseif ( $_POST[ 'action' ] === 'delete_admin' && ! empty( $_POST[ 'admin' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				$msg .= '<div class="red" role="alert">Warning: This will permanently delete the admin account "' . htmlspecialchars( $_POST[ 'admin' ] ) . '". It cannot be reversed. Are you absolutely sure?</div>';
				$msg .= '<form method="post"><input type="hidden" name="csrf_token" value="' . $_SESSION[ 'csrf_token' ] . '">';
				$msg .= '<input type="hidden" name="admin" value="' . htmlspecialchars( $_POST[ 'admin' ] ) . '">';
				$msg .= '<button type="submit" name="action" value="delete_admin2">Yes, I want to permanently delete this admin account</button></form>';
			} elseif ( $_POST[ 'action' ] === 'delete_domain' && ! empty( $_POST[ 'domain' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				$msg .= '<div class="red" role="alert">Warning: This will permanently delete the domain "' . htmlspecialchars( $_POST[ 'domain' ] ) . '". It cannot be reversed. Are you absolutely sure?</div>';
				$msg .= '<form method="post"><input type="hidden" name="csrf_token" value="' . $_SESSION[ 'csrf_token' ] . '">';
				$msg .= '<input type="hidden" name="domain" value="' . htmlspecialchars( $_POST[ 'domain' ] ) . '">';
				$msg .= '<button type="submit" name="action" value="delete_domain2">Yes, I want to permanently delete this domain</button></form>';
			} elseif ( $_POST[ 'action' ] === 'delete_alias_domain' && ! empty( $_POST[ 'alias_domain' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				$msg .= '<div class="red" role="alert">Warning: This will permanently delete the alias domain "' . htmlspecialchars( $_POST[ 'alias_domain' ] ) . '". It cannot be reversed. Are you absolutely sure?</div>';
				$msg .= '<form method="post"><input type="hidden" name="csrf_token" value="' . $_SESSION[ 'csrf_token' ] . '">';
				$msg .= '<input type="hidden" name="alias_domain" value="' . htmlspecialchars( $_POST[ 'alias_domain' ] ) . '">';
				$msg .= '<button type="submit" name="action" value="delete_alias_domain2">Yes, I want to permanently delete this alias domain</button></form>';
			} elseif ( $_POST[ 'action' ] === 'delete_alias' && ! empty( $_POST[ 'alias' ] ) ) {
				$msg .= '<div class="red" role="alert">Warning: This will permanently delete the alias "' . htmlspecialchars( $_POST[ 'alias' ] ) . '". It cannot be reversed. Are you absolutely sure?</div>';
				$msg .= '<form method="post"><input type="hidden" name="csrf_token" value="' . $_SESSION[ 'csrf_token' ] . '">';
				$msg .= '<input type="hidden" name="alias" value="' . htmlspecialchars( $_POST[ 'alias' ] ) . '">';
				$msg .= '<button type="submit" name="action" value="delete_alias2">Yes, I want to permanently delete this alias</button></form>';
			} elseif ( $_POST[ 'action' ] === 'delete_mailbox' && ! empty( $_POST[ 'user' ] ) ) {
				$msg .= '<div class="red" role="alert">Warning: This will permanently delete the alias "' . htmlspecialchars( $_POST[ 'user' ] ) . '". It cannot be reversed. Are you absolutely sure?</div>';
				$msg .= '<form method="post"><input type="hidden" name="csrf_token" value="' . $_SESSION[ 'csrf_token' ] . '">';
				$msg .= '<input type="hidden" name="user" value="' . htmlspecialchars( $_POST[ 'user' ] ) . '">';
				$msg .= '<button type="submit" name="action" value="delete_mailbox2">Yes, I want to permanently delete this mailbox</button></form>';
			} elseif ( $_POST[ 'action' ] === 'delete_admin2' && ! empty( $_POST[ 'admin' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				if ( $_SESSION[ 'email_admin_user' ] === $_POST[ 'admin' ] ) {
					$msg .= '<div class="red" role="alert">You can\'t delete your own admin account!</div>';
				} else {
					$stmt = $db->prepare( 'DELETE FROM admin WHERE username = ?;' );
					$stmt->execute( [ $_POST[ 'admin' ] ] );
					$msg .= '<div class="green" role="alert">Successfully deleted admin account.</div>';
				}
			} elseif ( $_POST[ 'action' ] === 'delete_domain2' && ! empty( $_POST[ 'domain' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				$stmt = $db->prepare( 'UPDATE domain SET active = -1 WHERE domain = ?;' );
				$stmt->execute( [ $_POST[ 'domain' ] ] );
				$msg .= '<div class="green" role="alert">Successfully deleted domain.</div>';
			} elseif ( $_POST[ 'action' ] === 'delete_alias_domain2' && ! empty( $_POST[ 'alias_domain' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				$stmt = $db->prepare( 'DELETE FROM alias_domain WHERE alias_domain = ?;' );
				$stmt->execute( [ $_POST[ 'alias_domain' ] ] );
				$msg .= '<div class="green" role="alert">Successfully deleted alias domain.</div>';
			} elseif ( $_POST[ 'action' ] === 'delete_alias2' && ! empty( $_POST[ 'alias' ] ) ) {
				if ( check_domain_access( $_POST[ 'alias' ], $msg ) ) {
					$stmt = $db->prepare( 'DELETE FROM alias WHERE address = ?;' );
					$stmt->execute( [ $_POST[ 'alias' ] ] );
					$msg .= '<div class="green" role="alert">Successfully deleted alias.</div>';
				}
			} elseif ( $_POST[ 'action' ] === 'delete_mailbox2' && ! empty( $_POST[ 'user' ] ) ) {
				if ( check_domain_access( $_POST[ 'user' ], $msg ) ) {
					$stmt = $db->prepare( 'UPDATE mailbox SET active = -2 WHERE username = ?;' );
					$stmt->execute( [ $_POST[ 'user' ] ] );
					$msg .= '<div class="green" role="alert">Successfully deleted mailbox.</div>';
				}
			} elseif ( $_POST[ 'action' ] === 'save_edit_admin' && ! empty( $_POST[ 'admin' ] ) && ( $_SESSION[ 'email_admin_superadmin' ] || $_POST[ 'admin' ] === $_SESSION[ 'email_admin_user' ] ) ) {
				$stmt = $db->prepare( 'SELECT null FROM admin WHERE username = ?;' );
				$stmt->execute( [ $_POST[ 'admin' ] ] );
				if ( ! $stmt->fetch() ) {
					$msg .= '<div class="red" role="alert">Oops, it looks like the admin account "' . htmlspecialchars( $_POST[ 'admin' ] ) . '" doesn\'t exist.</div>';
				} else {
					if ( ! empty( $_POST[ 'pass_update' ] ) ) {
						if ( empty( $_POST[ 'pass_update2' ] ) || $_POST[ 'pass_update' ] !== $_POST[ 'pass_update2' ] ) {
							$msg .= '<div class="red" role="alert">Passwords don\'t match!</div>';
						} else {
							$hash = password_hash( $_POST[ 'pass_update' ], PASSWORD_ARGON2ID );
							$stmt = $db->prepare( 'UPDATE admin SET password_hash_type = "{ARGON2ID}", password = ?, modified = NOW() WHERE username = ?;' );
							$stmt->execute( [ $hash, $_POST[ 'admin' ] ] );
							$msg .= '<div class="green" role="alert">Successfully updated password.</div>';
						}
					}
					if ( $_SESSION[ 'email_admin_superadmin' ] ) {
						if ( $_POST[ 'admin' ] !== $_SESSION[ 'email_admin_user' ] ) {
							$active = isset( $_POST[ 'active' ] ) ? 1 : 0;
							$superadmin = isset( $_POST[ 'superadmin' ] ) ? 1 : 0;
							$stmt = $db->prepare( 'UPDATE admin SET superadmin = ?, active = ?, modified = NOW() WHERE username = ?;' );
							$stmt->execute( [ $superadmin, $active, $_POST[ 'admin' ] ] );
						}
						$managed_domains = [];
						$stmt = $db->prepare( 'SELECT domain FROM domain_admins WHERE username = ?;' );
						$stmt->execute( [ $_POST[ 'admin' ] ] );
						while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
							$managed_domains [] = $tmp[ 'domain' ];
						}
						foreach ( $managed_domains as $domain ) {
							if ( ! in_array( $domain, $_POST[ 'domains' ], true ) ) {
								$stmt = $db->prepare( 'DELETE FROM domain_admins WHERE username = ? AND domain = ?;' );
								$stmt->execute( [ $_POST[ 'admin' ], $domain ] );
							}
						}
						foreach ( $_POST[ 'domains' ] as $domain ) {
							if ( ! in_array( $domain, $managed_domains, true ) ) {
								$stmt = $db->prepare( 'INSERT INTO domain_admins (username, domain) VALUES (?, ?);' );
								$stmt->execute( [ $_POST[ 'admin' ], $domain ] );
							}
						}
					}
					$msg .= '<div class="green" role="alert">Successfully edited admin account.</div>';
				}
			} elseif ( $_POST[ 'action' ] === 'save_new_admin' && ! empty( $_POST[ 'admin' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				$stmt = $db->prepare( 'SELECT null FROM admin WHERE username = ?;' );
				$stmt->execute( [ $_POST[ 'admin' ] ] );
				if ( $stmt->fetch() ) {
					$msg .= '<div class="red" role="alert">Oops, it looks like the admin account "' . htmlspecialchars( $_POST[ 'admin' ] ) . '" already exists.</div>';
				} else {
					if ( empty( $_POST[ 'pass_update2' ] ) || $_POST[ 'pass_update' ] !== $_POST[ 'pass_update2' ] ) {
						$msg .= '<div class="red" role="alert">Passwords empty or don\'t match!</div>';
					} else {
						$hash = password_hash( $_POST[ 'pass_update' ], PASSWORD_ARGON2ID );
						$active = isset( $_POST[ 'active' ] ) ? 1 : 0;
						$superadmin = isset( $_POST[ 'superadmin' ] ) ? 1 : 0;
						$stmt = $db->prepare( 'INSERT INTO admin (password_hash_type, password, superadmin, active, username, created, modified) VALUES ("{ARGON2ID}", ?, ?, ?, ?, NOW(), NOW());' );
						$stmt->execute( [ $hash, $superadmin, $active, $_POST[ 'admin' ] ] );
						$msg .= '<div class="green" role="alert">Successfully created admin account.</div>';
					}
				}
			} elseif ( $_POST[ 'action' ] === 'save_edit_domain' && ! empty( $_POST[ 'domain' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				$stmt = $db->prepare( 'SELECT null FROM domain WHERE domain = ?;' );
				$stmt->execute( [ $_POST[ 'domain' ] ] );
				if ( ! $stmt->fetch() ) {
					$msg .= '<div class="red" role="alert">Oops, it looks like the domain "' . htmlspecialchars( $_POST[ 'domain' ] ) . '" doesn\'t exists.</div>';
				} else {
					$active = isset( $_POST[ 'active' ] ) ? 1 : 0;
					$stmt = $db->prepare( 'UPDATE domain set active = ?, modified = NOW() WHERE domain = ?;' );
					$stmt->execute( [ $active, $_POST[ 'domain' ] ] );
					$msg .= '<div class="green" role="alert">Successfully updated domain.</div>';
				}
			} elseif ( $_POST[ 'action' ] === 'save_edit_alis_domain' && ! empty( $_POST[ 'alias_domain' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				$stmt = $db->prepare( 'SELECT null FROM alias_domain WHERE alias_domain = ?;' );
				$stmt->execute( [ $_POST[ 'alias_domain' ] ] );
				if ( ! $stmt->fetch() ) {
					$msg .= '<div class="red" role="alert">Oops, it looks like the alias domain "' . htmlspecialchars( $_POST[ 'alias_domain' ] ) . '" doesn\'t exists.</div>';
				} else {
					$active = isset( $_POST[ 'active' ] ) ? 1 : 0;
					$stmt = $db->prepare( 'UPDATE alias_domain set active = ?, modified = NOW() WHERE alias_domain = ?;' );
					$stmt->execute( [ $active, $_POST[ 'alias_domain' ] ] );
					$msg .= '<div class="green" role="alert">Successfully updated alias domain.</div>';
				}
			} elseif ( $_POST[ 'action' ] === 'save_new_domain' && ! empty( $_POST[ 'domain' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				$stmt = $db->prepare( 'SELECT null FROM domain WHERE domain = ?;' );
				$stmt->execute( [ $_POST[ 'domain' ] ] );
				if ( $stmt->fetch() ) {
					$msg .= '<div class="red" role="alert">Oops, it looks like the domain "' . htmlspecialchars( $_POST[ 'domain' ] ) . '" already exists.</div>';
				} else {
					$active = isset( $_POST[ 'active' ] ) ? 1 : 0;
					$stmt = $db->prepare( 'INSERT INTO domain (active, domain, created, modified) VALUES (?, ?, NOW(), NOW());' );
					$stmt->execute( [ $active, $_POST[ 'domain' ] ] );
					$msg .= '<div class="green" role="alert">Successfully created domain.</div>';
				}
			} elseif ( $_POST[ 'action' ] === 'save_new_alias_domain' && ! empty( $_POST[ 'alias_domain' ] ) && $_SESSION[ 'email_admin_superadmin' ] ) {
				$stmt = $db->prepare( 'SELECT null FROM alias_domain WHERE alias_domain = ?;' );
				$stmt->execute( [ $_POST[ 'alias_domain' ] ] );
				if ( $stmt->fetch() ) {
					$msg .= '<div class="red" role="alert">Oops, it looks like the alias domain "' . htmlspecialchars( $_POST[ 'domain' ] ) . '" already exists.</div>';
				} else {
					$active = isset( $_POST[ 'active' ] ) ? 1 : 0;
					$stmt = $db->prepare( 'INSERT INTO alias_domain (active, alias_domain, target_domain, created, modified) VALUES (?, ?, ?, NOW(), NOW());' );
					$stmt->execute( [ $active, $_POST[ 'alias_domain' ], $_POST[ 'target_domain' ] ] );
					$msg .= '<div class="green" role="alert">Successfully created alias domain.</div>';
				}
			} elseif ( $_POST[ 'action' ] === 'save_new_alias' && ! empty( $_POST[ 'alias' ] ) && ! empty( $_POST[ 'target' ] ) ) {
				$ok = check_email_valid( $_POST[ 'alias' ], $msg );
				if ( $ok ) {
					$ok = check_domain_access( $_POST[ 'alias' ], $msg );
				}
				if ( $ok ) {
					$targets = preg_split( "/[\s,]+/", $_POST[ 'target' ] );
					$alias_goto = validate_email_list( $targets, $msg );
					$stmt = $db->prepare( 'SELECT null FROM alias WHERE address = ?;' );
					$stmt->execute( [ $_POST[ 'alias' ] ] );
					if ( $stmt->fetch() ) {
						$msg .= '<div class="red" role="alert">Oops, it looks like the alias "' . htmlspecialchars( $_POST[ 'alias' ] ) . '" already exists.</div>';
					} else {
						$parser = new EmailParser( new EmailLexer() );
						$parser->parse( $_POST[ 'alias' ] );
						$domain = $parser->getDomainPart();
						$active = isset( $_POST[ 'active' ] ) ? 1 : 0;
						$enforce_tls_in = isset( $_POST[ 'enforce_tls_in' ] ) ? 1 : 0;
						$stmt = $db->prepare( 'INSERT INTO alias (goto, address, domain, active, created, modified, enforce_tls_in) VALUES (?, ?, ?, ?, NOW(), NOW(), ?);' );
						$stmt->execute( [ $alias_goto, $_POST[ 'alias' ], $domain, $active, $enforce_tls_in ] );
						$msg .= '<div class="green" role="alert">Successfully added alias.</div>';
					}
				}
			} elseif ( $_POST[ 'action' ] === 'save_edit_alias' && ! empty( $_POST[ 'alias' ] ) && ! empty( $_POST[ 'target' ] ) ) {
				$ok = check_email_valid( $_POST[ 'alias' ], $msg );
				if ( $ok ) {
					$ok = check_domain_access( $_POST[ 'alias' ], $msg );
				}
				if ( $ok ) {
					$targets = preg_split( "/[\s,]+/", $_POST[ 'target' ] );
					$alias_goto = validate_email_list( $targets, $msg );
					$active = isset( $_POST[ 'active' ] ) ? 1 : 0;
					$enforce_tls_in = isset( $_POST[ 'enforce_tls_in' ] ) ? 1 : 0;
					$stmt = $db->prepare( 'UPDATE alias SET goto = ?, active = ?, enforce_tls_in = ?, modified = NOW() WHERE address = ?;' );
					$stmt->execute( [ $alias_goto, $active, $enforce_tls_in, $_POST[ 'alias' ] ] );
					$msg .= '<div class="green" role="alert">Successfully updated alias.</div>';
				}
			} elseif ( $_POST[ 'action' ] === 'save_edit_mailbox' && ! empty( $_POST[ 'user' ] ) ) {
				$ok = check_email_valid( $_POST[ 'user' ], $msg );
				if ( $ok ) {
					$ok = check_domain_access( $_POST[ 'user' ], $msg );
				}
				if ( $ok ) {
					$alias_goto = '';
					if ( isset( $_POST[ 'alias_keep_copy' ] ) ) {
						$alias_goto .= $_POST[ 'user' ] . ',';
					}
					if ( ! empty( $_POST[ 'alias_to' ] ) ) {
						$additional = preg_split( "/[\s,]+/", $_POST[ 'alias_to' ] );
						$alias_goto .= validate_email_list( $additional, $msg );
					}
					$quota = 1024 * 1024 * 1024;
					$alias_goto = rtrim( $alias_goto, ',' );
					$stmt = $db->prepare( 'UPDATE alias SET goto = ?, enforce_tls_in = ?, active = ? WHERE address = ?;' );
					$stmt->execute( [ $alias_goto, ( isset( $_POST[ 'enforce_tls_in' ] ) ? 1 : 0 ), ( isset( $_POST[ 'active' ] ) ? 1 : 0 ), $_POST[ 'user' ] ] );
					$stmt = $db->prepare( 'UPDATE mailbox SET enforce_tls_in = ?, enforce_tls_out = ?, active = ?, quota = ?, modified = NOW() WHERE username = ?;' );
					$stmt->execute( [ ( isset( $_POST[ 'enforce_tls_in' ] ) ? 1 : 0 ), ( isset( $_POST[ 'enforce_tls_out' ] ) ? 1 : 0 ), ( isset( $_POST[ 'active' ] ) ? 1 : 0 ), $quota, $_POST[ 'user' ] ] );
					$msg .= '<div class="green" role="alert">Successfully updated mailbox.</div>';
				}
			} elseif ( $_POST[ 'action' ] === 'save_new_mailbox' && ! empty( $_POST[ 'user' ] ) ) {
				$email = $_POST[ 'user' ];
				$ok = check_email_valid( $email, $msg );
				if ( $ok ) {
					$ok = check_domain_access( $email, $msg );
				}
				if ( $ok ) {
					$stmt = $db->prepare( 'SELECT null FROM mailbox WHERE username = ? UNION SELECT null FROM alias WHERE address = ?;' );
					$stmt->execute( [ $email, $email ] );
					if ( $stmt->fetch() ) {
						$ok = false;
						$msg .= '<div class="red" role="alert">Sorry, this user already exists</div>';
					}
					if ( $ok ) {
						$parser = new EmailParser( new EmailLexer() );
						$parser->parse( $email );
						$user = $parser->getLocalPart();
						$domain = $parser->getDomainPart();
						$hash = password_hash( $_POST[ 'pwd' ], PASSWORD_ARGON2ID );
						$quota = 50 * 1024 * 1024;
						$alias_goto = '';
						if ( isset( $_POST[ 'alias_keep_copy' ] ) ) {
							$alias_goto .= $email . ',';
						}
						if ( ! empty( $_POST[ 'alias_to' ] ) ) {
							$additional = preg_split( "/[\s,]+/", $_POST[ 'alias_to' ] );
							$alias_goto .= validate_email_list( $additional, $msg );
						}
						$alias_goto = rtrim( $alias_goto, ',' );
						$stmt = $db->prepare( 'INSERT INTO alias (address, goto, domain, created, modified, enforce_tls_in, active) VALUES (?, ?, ?, NOW(), NOW(), ?, ?);' );
						$stmt->execute( [ $email, $alias_goto, $domain, ( isset( $_POST[ 'enforce_tls_in' ] ) ? 1 : 0 ), ( isset( $_POST[ 'active' ] ) ? 1 : 0 ) ] );
						$stmt = $db->prepare( 'INSERT INTO mailbox (username, password, quota, local_part, domain, created, modified, password_hash_type, openpgpkey_wkd, enforce_tls_in, enforce_tls_out, active) VALUES(?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?);' );
						$stmt->execute( [ $email, $hash, $quota, $user, $domain, '{ARGON2ID}', z_base32_encode( hash( 'sha1', mb_strtolower( $user ), true ) ), ( isset( $_POST[ 'enforce_tls_in' ] ) ? 1 : 0 ), ( isset( $_POST[ 'enforce_tls_out' ] ) ? 1 : 0 ), ( isset( $_POST[ 'active' ] ) ? 1 : 0 ) ] );
						$msg .= '<div class="green" role="alert">Successfully created new mailbox!</div>';
					}
				}
			} elseif ( $_POST[ 'action' ] === 'save_password_mailbox' && ! empty( $_POST[ 'user' ] ) ) {
				$ok = check_email_valid( $_POST[ 'user' ], $msg );
				if ( $ok ) {
					$ok = check_domain_access( $_POST[ 'user' ], $msg );
				}
				if ( $ok ) {
					if ( empty( $_POST[ 'pass_update' ] ) || empty( $_POST[ 'pass_update2' ] ) || $_POST[ 'pass_update' ] !== $_POST[ 'pass_update2' ] ) {
						$msg .= '<div class="red" role="alert">Passwords empty or don\'t match</div>';
					} else {
						$hash = password_hash( $_POST[ 'pass_update' ], PASSWORD_ARGON2ID );
						$stmt = $db->prepare( 'UPDATE mailbox SET password_hash_type = "{ARGON2ID}", password = ? WHERE username = ?;' );
						$stmt->execute( [ $hash, $_POST[ 'user' ] ] );
						$msg .= '<div class="green" role="alert">Successfully updated password</div>';
					}
				}
			} elseif ( $_POST[ 'action' ] === 'disable_tfa_mailbox' && ! empty( $_POST[ 'user' ] ) ) {
				$ok = check_email_valid( $_POST[ 'user' ], $msg );
				if ( $ok ) {
					$ok = check_domain_access( $_POST[ 'user' ], $msg );
				}
				if ( $ok ) {
					$stmt = $db->prepare( 'UPDATE mailbox SET tfa = 0 WHERE username = ?;' );
					$stmt->execute( [ $_POST[ 'user' ] ] );
					$msg .= '<div class="green" role="alert">Successfully disabled two factor authentication</div>';
				}
			}
		}
	}
}
?>
<!DOCTYPE html>
    <html lang="en-gb">
    <head>
        <title>Daniel - E-Mail and XMPP - Admin management</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="author" content="Daniel Winzen">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Lets admins manage their email domain and user accounts.">
        <link rel="canonical" href="https://danwin1210.de/mail/admin.php">
    </head>
    <body>
        <main>
	<?php if ( ! empty( $_SESSION[ 'email_admin_user' ] ) ) { ?>
        <form method="post"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <p>Logged in as <?php echo htmlspecialchars( $_SESSION[ 'email_admin_user' ] ); ?> |
            <button name="action" value="logout" type="submit">Logout</button><?php
			if ( $_SESSION[ 'email_admin_superadmin' ] ) {
				?> | <a href="?action=admins">Manage admins</a><?php
				?> | <a href="?action=alias_domains">Manage alias domains</a><?php
			} else {
				?> | <a href="?action=edit_admin">Manage your admin account</a><?php
			}
			?> | <a href="?action=domains">Manage domains</a><?php
			?> | <a href="?action=alias">Manage aliases</a><?php
			?> | <a href="?action=mailbox">Manage mailboxes</a><?php
			?></p></form><?php
	}
	echo "<p>$msg</p>";
	if ( empty( $_SESSION[ 'email_admin_user' ] ) ) { ?>
        <form class="form_limit" action="admin.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <div class="row">
                <div class="col"><label for="user">Username</label></div>
                <div class="col"><input type="text" name="user" id="user" autocomplete="username" required></div>
            </div>
            <div class="row">
                <div class="col"><label for="pwd">Password</label></div>
                <div class="col"><input type="password" name="pwd" id="pwd" autocomplete="new-password" required></div>
            </div>
            <div class="row">
                <div class="col">
                    <button name="action" value="login" type="submit">Login</button>
                </div>
            </div>
        </form>
	<?php } else {
		if ( empty( $_REQUEST[ 'action' ] ) || $_REQUEST[ 'action' ] === 'login' ) {
			?><p>Welcome to the admin management interface. You can configure your domain(s) and accounts here. Please
                select an option from the menu.</p><?php
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'admins', 'delete_admin2' ], true ) && $_SESSION[ 'email_admin_superadmin' ] ) {
			send_manage_admins();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'domains', 'delete_domain2' ], true ) ) {
			send_manage_domains();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'alias_domains', 'delete_alias_domain2' ], true ) && $_SESSION[ 'email_admin_superadmin' ] ) {
			send_manage_alias_domains();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'alias', 'delete_alias2' ], true ) ) {
			send_manage_aliases();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'mailbox', 'delete_mailbox2' ], true ) ) {
			send_manage_mailboxes();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'new_admin', 'save_new_admin' ], true ) && $_SESSION[ 'email_admin_superadmin' ] ) {
			send_new_admin();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'new_domain', 'save_new_domain' ], true ) && $_SESSION[ 'email_admin_superadmin' ] ) {
			send_new_domain();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'new_alias_domain', 'save_new_alias_domain' ], true ) && $_SESSION[ 'email_admin_superadmin' ] ) {
			send_new_alias_domain();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'new_alias', 'save_new_alias' ], true ) ) {
			send_new_alias();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'new_mailbox', 'save_new_mailbox' ], true ) ) {
			send_new_mailbox();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'edit_admin', 'save_edit_admin' ], true ) ) {
			send_edit_admin();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'edit_domain', 'save_edit_domain' ], true ) ) {
			send_edit_domain();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'edit_alias_domain', 'save_edit_alias_domain' ], true ) && $_SESSION[ 'email_admin_superadmin' ] ) {
			send_edit_alias_domain();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'edit_alias', 'save_edit_alias' ], true ) ) {
			send_edit_alias();
		} elseif ( in_array( $_REQUEST[ 'action' ], [ 'edit_mailbox', 'save_edit_mailbox', 'save_password_mailbox', 'disable_tfa_mailbox' ], true ) ) {
			send_edit_mailbox();
		} elseif ( empty( $msg ) ) {
			?><p>Oops, it looks like the page you tried to access does not exist or you do not have permission to access
                it.</p><?php
		}
	} ?>
    </main>
    </body>
</html>

<?php
function send_manage_admins(): void
{
	$db = get_db_instance();
	$stmt = $db->query( 'SELECT username, modified, active FROM admin;' );
	?>
    <p><a href="?action=new_admin">Create new admin</a></p>
    <form class="form_limit" action="admin.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <input type="hidden" name="action" value="edit_admin">
        <div class="row">
            <div class="col">Admin</div>
            <div class="col">Active</div>
            <div class="col">Last modified</div>
            <div class="col">Edit account</div>
        </div>
		<?php
		while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$active = 'Disabled';
			if ( $tmp[ 'active' ] === 1 ) {
				$active = 'Active';
			}
			echo '<div class="row"><div class="col">' . htmlspecialchars( $tmp[ 'username' ] ) . '</div><div class="col">' . $active . '</div><div class="col">' . $tmp[ 'modified' ] . '</div><div class="col"><button type="submit" name="admin" value="' . htmlspecialchars( $tmp[ 'username' ] ) . '">Edit</button></div></div>';
		}
		?></form>
    <p><a href="?action=new_admin">Create new admin</a></p>
	<?php
}

function send_edit_admin(): void
{
	$db = get_db_instance();
	$admin = $_POST[ 'admin' ] ?? $_SESSION[ 'email_admin_user' ];
	$stmt = $db->prepare( 'SELECT username, superadmin, active FROM admin WHERE username = ?;' );
	$stmt->execute( [ $admin ] );
	if ( $admin = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
		?>
        <h2>Edit admin account <?php echo htmlspecialchars( $admin[ 'username' ] ); ?></h2>
        <form class="form_limit" action="admin.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <input type="hidden" name="admin" value="<?php echo htmlspecialchars( $admin[ 'username' ] ); ?>"
                   autocomplete="username">
            <div class="row">
                <div class="col"><label for="pass_update">Password</label></div>
                <div class="col"><input type="password" name="pass_update" id="pass_update" autocomplete="new-password">
                </div>
            </div>
            <div class="row">
                <div class="col"><label for="pass_update2">Password again</label></div>
                <div class="col"><input type="password" name="pass_update2" id="pass_update2"
                                        autocomplete="new-password"></div>
            </div>
			<?php if ( $admin[ 'username' ] !== $_SESSION[ 'email_admin_user' ] ) { ?>
                <div class="row">
                    <div class="col"><label><input type="checkbox" name="superadmin"
                                                   value="1"<?php echo $admin[ 'superadmin' ] ? ' checked' : ''; ?>>Superadmin</label>
                    </div>
                    <div class="col">Superadmins can manage other admins</div>
                </div>
                <div class="row">
                    <div class="col"><label><input type="checkbox" name="active"
                                                   value="1"<?php echo $admin[ 'active' ] ? ' checked' : ''; ?>>Active</label>
                    </div>
                </div>
			<?php } else { ?>
                <div class="row">
                    <div class="col"><label><input type="checkbox" name="superadmin"
                                                   value="1"<?php echo $admin[ 'superadmin' ] ? ' checked' : ''; ?>
                                                   disabled>Superadmin</label></div>
                    <div class="col">Superadmins can manage other admins</div>
                </div>
                <div class="row">
                    <div class="col"><label><input type="checkbox" name="active"
                                                   value="1"<?php echo $admin[ 'active' ] ? ' checked' : ''; ?>
                                                   disabled>Active</label></div>
                </div>
			<?php } ?>
            <div class="row">
                <div class="col"><label for="domains">Managed domains</label></div>
                <div class="col"><select name="domains[]" id="domains" multiple><?php
						$domains = [];
						$managed_domains = [];
						$stmt = $db->query( 'SELECT domain FROM domain;' );
						while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
							$domains [] = $tmp[ 'domain' ];
						}
						$stmt = $db->prepare( 'SELECT domain FROM domain_admins WHERE username = ?;' );
						$stmt->execute( [ $admin[ 'username' ] ] );
						while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
							$managed_domains [] = $tmp[ 'domain' ];
						}
						foreach ( $domains as $domain ) {
							echo '<option value="' . htmlspecialchars( $domain ) . '"' . ( in_array( $domain, $managed_domains, true ) ? ' selected' : '' ) . '>' . htmlspecialchars( $domain ) . '</value>';
						}
						?></select></div>
            </div>
            <div class="row">
                <div class="col">
                    <button name="action" value="save_edit_admin" type="submit">Save changes</button>
                </div>
            </div>
			<?php if ( $admin[ 'username' ] !== $_SESSION[ 'email_admin_user' ] ) { ?>
                <div class="row">
                    <div class="col">
                        <button type="submit" name="action" value="delete_admin">Delete admin</button>
                    </div>
                </div>
			<?php } ?>
        </form>
		<?php
	} else {
		echo '<p>Oops, this admin doesn\'t seem to exist.</p>';
	}
}

function send_new_admin(): void
{
	?>
    <h2>Create new admin account</h2>
    <form class="form_limit" action="admin.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="admin">Username</label></div>
            <div class="col"><input type="text" name="admin" id="admin" autocomplete="username"></div>
        </div>
        <div class="row">
            <div class="col"><label for="pass_update">Password</label></div>
            <div class="col"><input type="password" name="pass_update" id="pass_update" autocomplete="new-password">
            </div>
        </div>
        <div class="row">
            <div class="col"><label for="pass_update2">Password again</label></div>
            <div class="col"><input type="password" name="pass_update2" id="pass_update2" autocomplete="new-password">
            </div>
        </div>
        <div class="row">
            <div class="col"><label><input type="checkbox" name="superadmin" value="1">Superadmin</label></div>
            <div class="col">Superadmins can manage other admins</div>
        </div>
        <div class="row">
            <div class="col"><label><input type="checkbox" name="active" value="1">Active</label></div>
        </div>
        <div class="row">
            <div class="col">
                <button name="action" value="save_new_admin" type="submit">Add admin</button>
            </div>
        </div>
    </form>
	<?php
}

function send_manage_domains(): void
{
	$db = get_db_instance();
	$stmt = $db->query( 'SELECT domain, modified, active FROM domain;' );
	if ( $_SESSION[ 'email_admin_superadmin' ] ) {
		?>
        <p><a href="?action=new_domain">Create new domain</a></p>
	<?php } ?>
    <form class="form_limit" action="admin.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <input type="hidden" name="action" value="edit_domain">
        <div class="row">
            <div class="col">Domain</div>
            <div class="col">Active</div>
            <div class="col">Last modified</div>
            <div class="col">Edit domain</div>
        </div>
		<?php
		while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$active = 'Disabled';
			if ( $tmp[ 'active' ] === 1 ) {
				$active = 'Active';
			} elseif ( $tmp[ 'active' ] === -1 ) {
				$active = 'Deleting';
			}
			echo '<div class="row"><div class="col">' . htmlspecialchars( $tmp[ 'domain' ] ) . '</div><div class="col">' . $active . '</div><div class="col">' . $tmp[ 'modified' ] . '</div><div class="col"><button type="submit" name="domain" value="' . htmlspecialchars( $tmp[ 'domain' ] ) . '">Edit</button></div></div>';
		}
		?></form>
	<?php if ( $_SESSION[ 'email_admin_superadmin' ] ) { ?>
    <p><a href="?action=new_domain">Create new domain</a></p>
	<?php
}
}

function send_new_domain(): void
{
	?>
    <h2>Create new domain</h2>
    <form class="form_limit" action="admin.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="domain">Domain</label></div>
            <div class="col"><input type="text" name="domain" id="domain"></div>
        </div>
        <div class="row">
            <div class="col"><label><input type="checkbox" name="active" value="1">Active</label></div>
        </div>
        <div class="row">
            <div class="col">
                <button name="action" value="save_new_domain" type="submit">Add domain</button>
            </div>
        </div>
    </form>
	<?php
}

function send_edit_domain(): void
{
	$db = get_db_instance();
	$stmt = $db->prepare( 'SELECT domain, active FROM domain WHERE domain = ?;' );
	$stmt->execute( [ $_POST[ 'domain' ] ] );
	if ( $admin = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
		?>
        <h2>Edit domain <?php echo htmlspecialchars( $_POST[ 'domain' ] ); ?></h2>
        <form class="form_limit" action="admin.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <input type="hidden" name="domain" value="<?php echo htmlspecialchars( $_POST[ 'domain' ] ); ?>">
            <div class="row">
                <div class="col"><label><input type="checkbox" name="active"
                                               value="1"<?php echo $admin[ 'active' ] ? ' checked' : ''; ?>>Active</label>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <button name="action" value="save_edit_domain" type="submit">Save changes</button>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <button type="submit" name="action" value="delete_domain">Delete domain</button>
                </div>
            </div>
        </form>
		<?php
	} else {
		echo '<p>Oops, this admin doesn\'t seem to exist.</p>';
	}
}

function send_manage_alias_domains(): void
{
	$db = get_db_instance();
	$stmt = $db->query( 'SELECT alias_domain, target_domain, modified, active FROM alias_domain;' );
	if ( $_SESSION[ 'email_admin_superadmin' ] ) {
		?>
        <p><a href="?action=new_alias_domain">Create new alias domain</a></p>
	<?php } ?>
    <form class="form_limit" action="admin.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <input type="hidden" name="action" value="edit_alias_domain">
        <div class="row">
            <div class="col">Alias Domain</div>
            <div class="col">Target Domain</div>
            <div class="col">Active</div>
            <div class="col">Last modified</div>
            <div class="col">Edit alias domain</div>
        </div>
		<?php
		while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$active = 'Disabled';
			if ( $tmp[ 'active' ] === 1 ) {
				$active = 'Active';
			}
			echo '<div class="row"><div class="col">' . htmlspecialchars( $tmp[ 'alias_domain' ] ) . '</div><div class="col">' . htmlspecialchars( $tmp[ 'target_domain' ] ) . '</div><div class="col">' . $active . '</div><div class="col">' . $tmp[ 'modified' ] . '</div><div class="col"><button type="submit" name="alias_domain" value="' . htmlspecialchars( $tmp[ 'alias_domain' ] ) . '">Edit</button></div></div>';
		}
		?></form>
	<?php if ( $_SESSION[ 'email_admin_superadmin' ] ) { ?>
    <p><a href="?action=new_alias_domain">Create new alias domain</a></p>
	<?php
}
}

function send_new_alias_domain(): void
{
	?>
    <h2>Create new alias domain</h2>
    <form class="form_limit" action="admin.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="alias_domain">Alias Domain</label></div>
            <div class="col"><input type="text" name="alias_domain" id="alias_domain"></div>
        </div>
        <div class="row">
            <div class="col"><label for="target_domain">Target Domain</label></div>
            <div class="col"><input type="text" name="target_domain" id="target_domain"></div>
        </div>
        <div class="row">
            <div class="col"><label><input type="checkbox" name="active" value="1">Active</label></div>
        </div>
        <div class="row">
            <div class="col">
                <button name="action" value="save_new_alias_domain" type="submit">Add alias domain</button>
            </div>
        </div>
    </form>
	<?php
}

function send_edit_alias_domain(): void
{
	$db = get_db_instance();
	$stmt = $db->prepare( 'SELECT alias_domain, target_domain, active FROM alias_domain WHERE alias_domain = ?;' );
	$stmt->execute( [ $_POST[ 'alias_domain' ] ] );
	if ( $alias = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
		?>
        <h2>Edit alias domain <?php echo htmlspecialchars( $_POST[ 'alias_domain' ] ); ?></h2>
        <form class="form_limit" action="admin.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <input type="hidden" name="alias_domain"
                   value="<?php echo htmlspecialchars( $_POST[ 'alias_domain' ] ); ?>">
            <div class="row">
                <div class="col"><label for="target_domain">Target Domain</label></div>
                <div class="col"><input type="text" name="target_domain" id="target_domain"
                                        value="<?php echo htmlspecialchars( $alias[ 'target_domain' ] ); ?>"></div>
            </div>
            <div class="row">
                <div class="col"><label><input type="checkbox" name="active"
                                               value="1"<?php echo $alias[ 'active' ] ? ' checked' : ''; ?>>Active</label>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <button name="action" value="save_edit_alias_domain" type="submit">Save changes</button>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <button type="submit" name="action" value="delete_alias_domain">Delete alias domain</button>
                </div>
            </div>
        </form>
		<?php
	} else {
		echo '<p>Oops, this alias domain doesn\'t seem to exist.</p>';
	}
}

function send_manage_aliases(): void
{
	$db = get_db_instance();
	$stmt = $db->prepare( 'SELECT a.address, a.goto, a.modified, a.active FROM alias AS a LEFT JOIN mailbox AS m ON (m.username=a.address AND m.active=1) WHERE a.domain IN (SELECT domain FROM domain_admins WHERE username = ?) AND isnull(m.username) limit 200;' );
	$stmt->execute( [ $_SESSION[ 'email_admin_user' ] ] );
	?>
    <p><a href="?action=new_alias">Create new alias</a></p>
    <form class="form_limit" action="admin.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <input type="hidden" name="action" value="edit_alias">
        <div class="row">
            <div class="col">Alias</div>
            <div class="col">Target</div>
            <div class="col">Active</div>
            <div class="col">Last modified</div>
            <div class="col">Edit alias</div>
        </div>
		<?php
		while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$active = 'Disabled';
			if ( $tmp[ 'active' ] === 1 ) {
				$active = 'Active';
			}
			echo '<div class="row"><div class="col">' . htmlspecialchars( $tmp[ 'address' ] ) . '</div><div class="col">' . htmlspecialchars( $tmp[ 'goto' ] ) . '</div><div class="col">' . $active . '</div><div class="col">' . $tmp[ 'modified' ] . '</div><div class="col"><button type="submit" name="alias" value="' . htmlspecialchars( $tmp[ 'address' ] ) . '">Edit</button></div></div>';
		}
		?></form>
    <p><a href="?action=new_alias">Create new alias</a></p>
	<?php
}

function send_new_alias(): void
{
	?>
    <h2>Create new alias</h2>
    <form class="form_limit" action="admin.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="alias">Alias</label></div>
            <div class="col"><input type="text" name="alias" id="alias"></div>
        </div>
        <div class="row">
            <div class="col"><label for="target">Target</label></div>
            <div class="col"><input type="text" name="target" id="target"></div>
        </div>
        <div class="row">
            <div class="col"><label><input type="checkbox" name="active" value="1">Active</label></div>
        </div>
        <div class="row">
            <div class="col"><label><input type="checkbox" name="enforce_tls_in" value="1">Enforce encryption</label>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <button name="action" value="save_new_alias" type="submit">Add alias</button>
            </div>
        </div>
    </form>
	<?php
}

function send_edit_alias(): void
{
	$db = get_db_instance();
	$stmt = $db->prepare( 'SELECT a.address, a.goto, a.active, a.enforce_tls_in FROM alias AS a LEFT JOIN mailbox AS m ON (m.username=a.address AND m.active=1) WHERE a.address = ? AND isnull(m.username);' );
	$stmt->execute( [ $_POST[ 'alias' ] ] );
	if ( $alias = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
		?>
        <h2>Edit alias <?php echo htmlspecialchars( $_POST[ 'alias' ] ); ?></h2>
        <form class="form_limit" action="admin.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <input type="hidden" name="alias" value="<?php echo htmlspecialchars( $_POST[ 'alias' ] ); ?>">
            <div class="row">
                <div class="col"><label for="target">Target</label></div>
                <div class="col"><textarea name="target"
                                           id="target"><?php echo str_replace( ',', "\n", htmlspecialchars( $alias[ 'goto' ] ) ); ?></textarea>
                </div>
            </div>
            <div class="row">
                <div class="col"><label><input type="checkbox" name="active"
                                               value="1"<?php echo $alias[ 'active' ] ? ' checked' : ''; ?>>Active</label>
                </div>
            </div>
            <div class="row">
                <div class="col"><label><input type="checkbox" name="enforce_tls_in"
                                               value="1"<?php echo $alias[ 'enforce_tls_in' ] ? ' checked' : ''; ?>>Enforce
                        encryption</label></div>
            </div>
            <div class="row">
                <div class="col">
                    <button name="action" value="save_edit_alias" type="submit">Save changes</button>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <button type="submit" name="action" value="delete_alias">Delete alias</button>
                </div>
            </div>
        </form>
		<?php
	} else {
		echo '<p>Oops, this alias doesn\'t seem to exist.</p>';
	}
}

function send_manage_mailboxes(): void
{
	$db = get_db_instance();
	$stmt = $db->prepare( 'SELECT username, modified, active FROM mailbox WHERE domain IN (SELECT domain FROM domain_admins WHERE username = ?) limit 200;' );
	$stmt->execute( [ $_SESSION[ 'email_admin_user' ] ] );
	?>
    <p><a href="?action=new_mailbox">Create new mailbox</a></p>
    <form class="form_limit" action="admin.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <input type="hidden" name="action" value="edit_mailbox">
        <div class="row">
            <div class="col">Username</div>
            <div class="col">Active</div>
            <div class="col">Last modified</div>
            <div class="col">Edit mailbox</div>
        </div>
		<?php
		while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$active = 'Disabled';
			if ( $tmp[ 'active' ] === 1 ) {
				$active = 'Active';
			} elseif ( $tmp[ 'active' ] === -1 ) {
				$active = 'Disabling';
			} elseif ( $tmp[ 'active' ] === -2 ) {
				$active = 'Deleting';
			}
			echo '<div class="row"><div class="col">' . htmlspecialchars( $tmp[ 'username' ] ) . '</div><div class="col">' . $active . '</div><div class="col">' . $tmp[ 'modified' ] . '</div><div class="col"><button type="submit" name="user" value="' . htmlspecialchars( $tmp[ 'username' ] ) . '">Edit</button></div></div>';
		}
		?></form>
    <p><a href="?action=new_mailbox">Create new mailbox</a></p>
	<?php
}

function send_new_mailbox(): void
{
	?>
    <h2>Create new mailbox</h2>
    <form class="form_limit" action="admin.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
        <div class="row">
            <div class="col"><label for="user">Username</label></div>
            <div class="col"><input type="text" name="user" id="user"></div>
        </div>
        <div class="row">
            <div class="col"><label for="pwd">Password</label></div>
            <div class="col"><input type="password" name="pwd" id="pwd" autocomplete="new-password" required></div>
        </div>
        <div class="row">
            <div class="col"><label for="pwd2">Password again</label></div>
            <div class="col"><input type="password" name="pwd2" id="pwd2" autocomplete="new-password" required></div>
        </div>
        <div class="row">
            <div class="col"><label for="alias_to">Forward to</label></div>
            <div class="col"><textarea name="alias_to" id="alias_to"></textarea></div>
        </div>
        <div class="row">
            <div class="col"><label for="alias_keep_copy">Keep a local copy</label></div>
            <div class="col"><input type="checkbox" name="alias_keep_copy" id="alias_keep_copy" checked></div>
        </div>
        <div class="row">
            <div class="col"><label><input type="checkbox" name="active" value="1" checked>Active</label></div>
        </div>
        <div class="row">
            <div class="col"><label><input type="checkbox" name="enforce_tls_in" value="1" checked>Enforce encryption
                    for incoming mail</label>
            </div>
        </div>
        <div class="row">
            <div class="col"><label><input type="checkbox" name="enforce_tls_out" value="1" checked>Enforce encryption
                    for outgoing mail</label>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <button name="action" value="save_new_mailbox" type="submit">Add mailbox</button>
            </div>
        </div>
    </form>
	<?php
}

function send_edit_mailbox(): void
{
	$db = get_db_instance();
	$stmt = $db->prepare( 'SELECT a.goto, m.active, m.enforce_tls_in, m.enforce_tls_out FROM alias AS a INNER JOIN mailbox AS m ON (m.username=a.address) WHERE m.username = ?;' );
	$stmt->execute( [ $_REQUEST[ 'user' ] ] );
	if ( $email = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
		$aliases = explode( ',', $email[ 'goto' ] );
		$aliases_to = implode( "\n", array_diff( $aliases, [ $_POST[ 'user' ] ] ) );
		?>
        <h2>Edit mailbox <?php echo htmlspecialchars( $_POST[ 'user' ] ); ?></h2>
        <form class="form_limit" action="admin.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <input type="hidden" name="user" value="<?php echo htmlspecialchars( $_POST[ 'user' ] ); ?>">
            <div class="row">
                <div class="col"><label for="alias_to">Forward to</label></div>
                <div class="col"><textarea name="alias_to"
                                           id="alias_to"><?php echo htmlspecialchars( $aliases_to ); ?></textarea></div>
            </div>
            <div class="row">
                <div class="col"><label for="alias_keep_copy">Keep a local copy</label></div>
                <div class="col"><input type="checkbox" name="alias_keep_copy"
                                        id="alias_keep_copy"<?php echo in_array( $_POST[ 'user' ], $aliases, true ) ? ' checked' : ''; ?>>
                </div>
            </div>
            <div class="row">
                <div class="col"><label><input type="checkbox" name="active"
                                               value="1"<?php echo $email[ 'active' ] === 1 ? ' checked' : ''; ?>>Active</label>
                </div>
            </div>
            <div class="row">
                <div class="col"><label><input type="checkbox" name="enforce_tls_in"
                                               value="1"<?php echo $email[ 'enforce_tls_in' ] === 1 ? ' checked' : ''; ?>>Enforce
                        encryption for incoming mail</label>
                </div>
            </div>
            <div class="row">
                <div class="col"><label><input type="checkbox" name="enforce_tls_out"
                                               value="1"<?php echo $email[ 'enforce_tls_out' ] === 1 ? ' checked' : ''; ?>>Enforce
                        encryption for outgoing mail</label>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <button name="action" value="save_edit_mailbox" type="submit">Save mailbox</button>
                </div>
            </div>
        </form>
        <h2>Change password</h2>
        <form class="form_limit" action="admin.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <input type="hidden" name="user" value="<?php echo htmlspecialchars( $_POST[ 'user' ] ); ?>">
            <div class="row">
                <div class="col"><label for="pass_update">Password</label></div>
                <div class="col"><input type="password" name="pass_update" id="pass_update" autocomplete="new-password"
                                        required></div>
            </div>
            <div class="row">
                <div class="col"><label for="pass_update2">Password again</label></div>
                <div class="col"><input type="password" name="pass_update2" id="pass_update2"
                                        autocomplete="new-password" required></div>
            </div>
            <div class="row">
                <div class="col">
                    <button name="action" value="save_password_mailbox" type="submit">Change password</button>
                </div>
            </div>
        </form>
        <h2>Delete mailbox / Disable two factor authentication</h2>
        <form class="form_limit" action="admin.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[ 'csrf_token' ]; ?>">
            <input type="hidden" name="user" value="<?php echo htmlspecialchars( $_POST[ 'user' ] ); ?>">
            <div class="row">
                <div class="col">
                    <button type="submit" name="action" value="disable_tfa_mailbox">Disable two factor authentication
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <button type="submit" name="action" value="delete_mailbox">Delete mailbox</button>
                </div>
            </div>
        </form>
		<?php
	} else {
		echo '<p>Oops, this mailbox doesn\'t seem to exist.</p>';
	}
}
