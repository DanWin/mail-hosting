<?php

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;

require_once( '../common_config.php' );
global $language, $dir, $locale;
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
		$msg .= '<div class="red" role="alert">'._('Invalid CSRF token').'</div>';
	}
	if ( ! check_captcha( $_POST[ 'challenge' ] ?? '', $_POST[ 'captcha' ] ?? '' ) ) {
		$ok = false;
		$msg .= '<div class="red" role="alert">'._('Invalid captcha').'</div>';
	}
	$db = get_db_instance();
	if ( ! preg_match( '/^([^+\/\'"]+?)(@([^@]+))?$/iu', $_POST[ 'user' ], $match ) ) {
		$ok = false;
		$msg .= '<div class="red" role="alert">'._('Invalid username. It may not contain a +, \', " or /.').'</div>';
	}
	$user = mb_strtolower( $match[ 1 ] ?? '' );
	$domain = $match[ 3 ] ?? 'danwin1210.de';
	if ( $ok && ( empty( $_POST[ 'pwd' ] ) || empty( $_POST[ 'pwd2' ] ) || $_POST[ 'pwd' ] !== $_POST[ 'pwd2' ] ) ) {
		$ok = false;
		$msg .= '<div class="red" role="alert">'._('Passwords empty or don\'t match').'</div>';
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
			$msg .= '<div class="red" role="alert">'._('The domain you specified is not allowed').'</div>';
		} else {
			$validator = new EmailValidator();
			if ( ! $validator->isValid( "$user@$domain", new NoRFCWarningsValidation() ) ) {
				$ok = false;
				$msg .= '<div class="red" role="alert">'._('The email address you specified is not valid').'</div>';
			} elseif(in_array($user, RESERVED_USERNAMES, true)){
				$ok = false;
				$msg .= '<div class="red" role="alert">'._('The username you specified is reserved').'</div>';
			}

		}
	}
	if ( $ok ) {
		$stmt = $db->prepare( 'SELECT null FROM mailbox WHERE username = ? UNION SELECT null FROM alias WHERE address = ?;' );
		$stmt->execute( [ "$user@$domain", "$user@$domain" ] );
		if ( $stmt->fetch() ) {
			$ok = false;
			$msg .= '<div class="red" role="alert">'._('Sorry, this user already exists').'</div>';
		}
		if ( $ok ) {
			$hash = password_hash( $_POST[ 'pwd' ], PASSWORD_ARGON2ID );
			$stmt = $db->prepare( 'INSERT INTO alias (address, goto, domain, created, modified) VALUES (?, ?, ?, NOW(), NOW());' );
			$stmt->execute( [ "$user@$domain", "$user@$domain", $domain ] );
			$stmt = $db->prepare( 'INSERT INTO mailbox (username, password, quota, local_part, domain, created, modified, password_hash_type, openpgpkey_wkd) VALUES(?, ?, 51200000, ?, ?, NOW(), NOW(), ?, ?);' );
			$stmt->execute( [ "$user@$domain", $hash, $user, $domain, '{ARGON2ID}', z_base32_encode( hash( 'sha1', mb_strtolower( $user ), true ) ) ] );
			$msg .= '<div class="green" role="alert">'._('Successfully created new mailbox!').'</div>';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>" dir="<?php echo $dir; ?>">
<head>
    <title><?php echo _('E-Mail and XMPP - Register'); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="author" content="Daniel Winzen">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo _('Register for a free and anonymous E-Mail address and an XMPP/Jabber account'); ?>">
    <link rel="canonical" href="<?php echo CANONICAL_URL; ?>register.php">
    <link rel="alternate" href="<?php echo CANONICAL_URL; ?>register.php" hreflang="x-default">
	<?php alt_links(); ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo _('E-Mail and XMPP - Register'); ?>">
    <meta property="og:description" content="<?php echo _('Register for a free and anonymous E-Mail address and an XMPP/Jabber account'); ?>">
    <meta property="og:url" content="<?php echo CANONICAL_URL; ?>register.php">
    <meta property="og:locale" content="<?php echo $locale; ?>">
    <script type="application/ld+json">{"@context":"https://schema.org","@type":"WebPage","name":"<?php echo _('E-Mail and XMPP - Register'); ?>", "description": "<?php echo _('Register for a free and anonymous E-Mail address and an XMPP/Jabber account'); ?>"}</script>
</head>
<body>
<main>
<p><a href="<?php echo ROOT_URL; ?>"><?php echo _('Info'); ?></a> | <?php echo _('Register'); ?> | <a href="<?php echo ROOT_URL; ?>squirrelmail/src/login.php" target="_blank"><?php echo _('Webmail-Login'); ?></a> |
    <a href="<?php echo ROOT_URL; ?>manage_account.php"><?php echo _('Manage account'); ?></a> | <a href="<?php echo WEB_XMPP_URL; ?>" target="_blank" rel="noopener"><?php echo _('Web-XMPP'); ?></a>
</p>
<?php echo "<p>$msg</p>"; ?>
<form class="form_limit" action="register.php" method="post"><input type="hidden" name="csrf_token"
                                                                    value="<?php echo $_SESSION[ 'csrf_token' ] ?>">
    <div class="row">
        <div class="col"><label for="user"><?php echo _('Username'); ?></label></div>
        <div class="col"><input type="text" name="user" id="user" autocomplete="username" required
                                value="<?php echo htmlspecialchars( $_POST[ 'user' ] ?? '' ); ?>"></div>
    </div>
    <div class="row">
        <div class="col"><label for="pwd"><?php echo _('Password'); ?></label></div>
        <div class="col"><input type="password" name="pwd" id="pwd" autocomplete="new-password" required></div>
    </div>
    <div class="row">
        <div class="col"><label for="pwd2"><?php echo _('Password again'); ?></label></div>
        <div class="col"><input type="password" name="pwd2" id="pwd2" autocomplete="new-password" required></div>
    </div>
    <div class="row">
        <div class="col"><label for="accept_privacy"><?php printf(_('I have read and agreed to the <a href="%s" target="_blank">Privacy Policy</a>'), PRIVACY_POLICY_URL); ?></label>
        </div>
        <div class="col"><input type="checkbox" id="accept_privacy" name="accept_privacy" required></div>
    </div>
	<?php send_captcha(); ?>
    <div class="row">
        <div class="col">
            <button type="submit"><?php echo _('Register'); ?></button>
        </div>
    </div>
</form>
</main>
</body>
</html>

