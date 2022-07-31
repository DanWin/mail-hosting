<?php

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;

require_once( '../vendor/autoload.php' );
require_once( '../common_config.php' );
session_start();
if ( empty( $_SESSION[ 'csrf_token' ] ) || $_SESSION[ 'UA' ] !== $_SERVER[ 'HTTP_USER_AGENT' ] ) {
	$_SESSION[ 'csrf_token' ] = sha1( uniqid() );
	$_SESSION[ 'UA' ] = $_SERVER[ 'HTTP_USER_AGENT' ];
}
$msg = '';
if ( isset( $_POST[ 'user' ] ) ) {
	$ok = true;
	if ( $_SESSION[ 'csrf_token' ] !== $_POST[ 'csrf_token' ] ?? '' ) {
		$ok = false;
		$msg .= '<div class="red" role="alert">Invalid csfr token</div>';
	}
	if ( ! check_captcha( $_POST[ 'challenge' ] ?? '', $_POST[ 'captcha' ] ?? '' ) ) {
		$ok = false;
		$msg .= '<div class="red" role="alert">Invalid captcha</div>';
	}
	$db = get_db_instance();
	if ( ! preg_match( '/^([^+\/\'"]+?)(@([^@]+))?$/iu', $_POST[ 'user' ], $match ) ) {
		$ok = false;
		$msg .= '<div class="red" role="alert">Invalid username. It may not contain a +, \', " or /.</div>';
	}
	$user = mb_strtolower( $match[ 1 ] ?? '' );
	$domain = $match[ 3 ] ?? 'danwin1210.de';
	if ( $ok && ( empty( $_POST[ 'pwd' ] ) || empty( $_POST[ 'pwd2' ] ) || $_POST[ 'pwd' ] !== $_POST[ 'pwd2' ] ) ) {
		$ok = false;
		$msg .= '<div class="red" role="alert">Passwords empty or don\'t match</div>';
	} elseif ( $ok ) {
		$stmt = $db->prepare( 'SELECT target_domain FROM alias_domain WHERE alias_domain = ? AND active=1;' );
		$stmt->execute( [ $domain ] );
		if ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$domain = $tmp[ 'target_domain' ];
		}
		$stmt = $db->prepare( 'SELECT null FROM domain WHERE domain = ? AND active = 1;' );
		$stmt->execute( [ $domain ] );
		if ( ! $stmt->fetch() ) {
			$ok = false;
			$msg .= '<div class="red" role="alert">The domain you specified is not allowed</div>';
		} else {
			$validator = new EmailValidator();
			if ( ! $validator->isValid( "$user@$domain", new NoRFCWarningsValidation() ) ) {
				$ok = false;
				$msg .= '<div class="red" role="alert">The email address you specified is not valid</div>';
			} elseif(in_array($user, RESERVED_USERNAMES, true)){
				$ok = false;
				$msg .= '<div class="red" role="alert">The user name you specified is reserved</div>';
			}

		}
	}
	if ( $ok ) {
		$stmt = $db->prepare( 'SELECT null FROM mailbox WHERE username = ? UNION SELECT null FROM alias WHERE address = ?;' );
		$stmt->execute( [ "$user@$domain", "$user@$domain" ] );
		if ( $stmt->fetch() ) {
			$ok = false;
			$msg .= '<div class="red" role="alert">Sorry, this user already exists</div>';
		}
		if ( $ok ) {
			$hash = password_hash( $_POST[ 'pwd' ], PASSWORD_ARGON2ID );
			$stmt = $db->prepare( 'INSERT INTO alias (address, goto, domain, created, modified) VALUES (?, ?, ?, NOW(), NOW());' );
			$stmt->execute( [ "$user@$domain", "$user@$domain", $domain ] );
			$stmt = $db->prepare( 'INSERT INTO mailbox (username, password, quota, local_part, domain, created, modified, password_hash_type, openpgpkey_wkd) VALUES(?, ?, 51200000, ?, ?, NOW(), NOW(), ?, ?);' );
			$stmt->execute( [ "$user@$domain", $hash, $user, $domain, '{ARGON2ID}', z_base32_encode( hash( 'sha1', mb_strtolower( $user ), true ) ) ] );
			$msg .= '<div class="green" role="alert">Successfully created new mailbox!</div>';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en-gb">
<head>
    <title>Daniel - E-Mail and XMPP - Register</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="author" content="Daniel Winzen">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Register for a free and anonymous E-Mail address and an XMPP/Jabber account">
    <link rel="canonical" href="https://danwin1210.de/mail/register.php">
</head>
<body>
<main>
<p><a href="/mail/">Info</a> | Register | <a href="/mail/squirrelmail/src/login.php" target="_blank">Webmail-Login</a> |
    <a href="/mail/manage_account.php">Manage account</a> | <a href="https://danwin1210.de:5281/conversejs" target="_blank" rel="noopener">Web-XMPP</a></p>
<?php echo "<p>$msg</p>"; ?>
<form class="form_limit" action="register.php" method="post"><input type="hidden" name="csrf_token"
                                                                    value="<?php echo $_SESSION[ 'csrf_token' ] ?>">
    <div class="row">
        <div class="col"><label for="user">Username</label></div>
        <div class="col"><input type="text" name="user" id="user" autocomplete="username" required
                                value="<?php echo htmlspecialchars( $_POST[ 'user' ] ?? '' ); ?>"></div>
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
        <div class="col"><label for="accept_privacy">I have read and agreed to the <a href="/privacy.php"
                                                                                      target="_blank">Privacy Policy</a></label>
        </div>
        <div class="col"><input type="checkbox" id="accept_privacy" name="accept_privacy" required></div>
    </div>
	<?php send_captcha(); ?>
    <div class="row">
        <div class="col">
            <button type="submit">Register</button>
        </div>
    </div>
</form>
</main>
</body>
</html>

