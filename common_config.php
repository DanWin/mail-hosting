<?php

use Egulias\EmailValidator\EmailLexer;
use Egulias\EmailValidator\EmailParser;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;

const DBHOST = 'localhost'; // Database host
const DBUSER = 'postfix'; // Database user
const DBPASS = 'YOUR_PASSWORD'; // Database password
const DBNAME = 'postfix'; // Database
const DBVERSION = 1; // Database schema version
const PERSISTENT = true; // persistent database connection
const CAPTCHA_DIFFICULTY = 1; // captcha difficulty from 0 to 4
const RESERVED_USERNAMES = ['about', 'abuse', 'admin', 'administrator', 'billing', 'contact', 'daemon', 'ftp', 'help', 'hostmaster', 'info', 'legal', 'list', 'list-request', 'lists', 'maildaemon', 'mailerdaemon', 'mailer-daemon', 'marketing', 'media', 'news', 'newsletter', 'nobody', 'noc', 'noreply', 'no-reply', 'notification', 'notifications', 'notify', 'offer', 'offers', 'office', 'official', 'order', 'orders', 'phish', 'phishing', 'postmaster', 'root', 'sale', 'sales', 'security', 'service', 'services', 'shop', 'shopping', 'spam', 'staff', 'support', 'survey', 'system', 'team', 'teams', 'unsbubscribe', 'uucp', 'usenet', 'user', 'username', 'users', 'web', 'webmail', 'webmaster', 'webmasters', 'welcome', 'www']; // list of reserved usernames that can mot be used on public registration
const CANONICAL_URL = 'https://danwin1210.de/mail/'; // our preferred URL prefix for search engines
const PRIVACY_POLICY_URL = '/privacy.php'; // URL to privacy policy
const WEB_XMPP_URL = 'https://danwin1210.de:5281/conversejs'; // URL to Web-XMPP
const XMPP_BOSH_URL = 'https://danwin1210.de:5281/http-bind'; // XMPP BOSH URL
const XMPP_FILE_PROXY = 'proxy.danwin1210.de'; // File proxy domain
const ROOT_URL = '/mail/'; // Relative root URL under which the mail hosting is installed
const CONTACT_URL = '/contact.php'; // URL to get in contact with you
const CLEARNET_SERVER = 'danwin1210.de'; // Clearnet domain of the mail server
const ONION_SERVER = 'danielas3rtn54uwmofdo3x2bsdifr47huasnmbgqzfrec5ubupvtpid.onion'; // Onion domain of the mail server
const DBHOST_PROSODY = 'localhost'; // Database host
const DBUSER_PROSODY = 'prosody'; // Database user
const DBPASS_PROSODY = 'YOUR_PASSWORD'; // Database password
const DBNAME_PROSODY = 'prosody'; // Database
const REGISTRATION_ENABLED = true; // Whether registration is enabled
const PRIMARY_DOMAIN='danwin1210.de'; // Primary domain to use when a username without domain part was specified

const LANGUAGES = [
	'cs' => ['name' => 'čeština', 'locale' => 'cs_CZ', 'flag' => '🇨🇿', 'show_in_menu' => true, 'dir' => 'ltr'],
	'de' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => '🇩🇪', 'show_in_menu' => true, 'dir' => 'ltr'],
	'en' => ['name' => 'English', 'locale' => 'en_GB', 'flag' => '🇬🇧', 'show_in_menu' => true, 'dir' => 'ltr'],
	'pl' => ['name' => 'Polski', 'locale' => 'pl_PL', 'flag' => '🇵🇱', 'show_in_menu' => true, 'dir' => 'ltr'],
	'ru' => ['name' => 'Русский', 'locale' => 'ru_RU', 'flag' => '🇷🇺', 'show_in_menu' => true, 'dir' => 'ltr'],
	'tr' => ['name' => 'Türkçe', 'locale' => 'tr_TR', 'flag' => '🇹🇷', 'show_in_menu' => true, 'dir' => 'ltr'],
	'uk' => ['name' => 'Українська', 'locale' => 'uk_UA', 'flag' => '🇺🇦', 'show_in_menu' => true, 'dir' => 'ltr'],
];
$language = 'en';
$locale = 'en_GB';
$dir = 'ltr';

if(isset($_REQUEST['lang']) && isset(LANGUAGES[$_REQUEST['lang']])){
	$locale = LANGUAGES[$_REQUEST['lang']]['locale'];
	$language = $_REQUEST['lang'];
	$dir = LANGUAGES[$_REQUEST['lang']]['dir'];
	setcookie('language', $_REQUEST['lang'], ['expires' => 0, 'path' => '/', 'domain' => '', 'secure' => ($_SERVER['HTTPS'] ?? '' === 'on'), 'httponly' => true, 'samesite' => 'Strict']);
}elseif(isset($_COOKIE['language']) && isset(LANGUAGES[$_COOKIE['language']])){
	$locale = LANGUAGES[$_COOKIE['language']]['locale'];
	$language = $_COOKIE['language'];
	$dir = LANGUAGES[$_COOKIE['language']]['dir'];
}elseif(!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
	$prefLocales = array_reduce(
		explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']),
		function (array $res, string $el) {
			list($l, $q) = array_merge(explode(';q=', $el), [1]);
			$res[$l] = (float) $q;
			return $res;
		}, []);
	arsort($prefLocales);
	foreach($prefLocales as $l => $q){
		$lang = locale_lookup(array_keys(LANGUAGES), $l);
		if(!empty($lang)){
			$locale = LANGUAGES[$lang]['locale'];
			$language = $lang;
			$dir = LANGUAGES[$lang]['dir'];
			setcookie('language', $lang, ['expires' => 0, 'path' => '/', 'domain' => '', 'secure' => ($_SERVER['HTTPS'] ?? '' === 'on'), 'httponly' => true, 'samesite' => 'Strict']);
			break;
		}
	}

}
putenv('LC_ALL='.$locale);
setlocale(LC_ALL, $locale);

bindtextdomain('mail-hosting', __DIR__.'/locale');
bind_textdomain_codeset('mail-hosting', 'UTF-8');
textdomain('mail-hosting');

require_once( 'vendor/autoload.php' );

function get_db_instance(): PDO
{
	static $db = null;
	if ( $db !== null ) {
		return $db;
	}
	try {
		$db = new PDO( 'mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT => PERSISTENT ] );
	} catch ( PDOException ) {
		http_response_code( 500 );
		die( _('No Connection to MySQL database!') );
	}
	return $db;
}

function z_base32_encode( string $input ): string
{
	$map = [
		'y', 'b', 'n', 'd', 'r', 'f', 'g', '8', //  7
		'e', 'j', 'k', 'm', 'c', 'p', 'q', 'x', // 15
		'o', 't', '1', 'u', 'w', 'i', 's', 'z', // 23
		'a', '3', '4', '5', 'h', '7', '6', '9', // 31
	];
	if ( empty( $input ) ) {
		return '';
	}
	$input = str_split( $input );
	$binaryString = '';
	$c = count( $input );
	for ( $i = 0; $i < $c; ++$i ) {
		$binaryString .= str_pad( decbin( ord( $input[ $i ] ) ), 8, '0', STR_PAD_LEFT );
	}
	$fiveBitBinaryArray = str_split( $binaryString, 5 );
	$base32 = '';
	$i = 0;
	$c = count( $fiveBitBinaryArray );
	while ( $i < $c ) {
		$base32 .= $map[ bindec( $fiveBitBinaryArray[ $i ] ) ];
		++$i;
	}
	return $base32;
}

function send_captcha(): void
{
	if ( CAPTCHA_DIFFICULTY === 0 || ! extension_loaded( 'gd' ) ) {
		return;
	}
	$db = get_db_instance();
	$captchachars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	$length = strlen( $captchachars ) - 1;
	$code = '';
	for ( $i = 0; $i < 5; ++$i ) {
		$code .= $captchachars[ mt_rand( 0, $length ) ];
	}
	$randid = mt_rand();
	$time = time();
	$stmt = $db->prepare( 'INSERT INTO captcha (id, time, code) VALUES (?, ?, ?);' );
	$stmt->execute( [ $randid, $time, $code ] );
	echo '<div class="row"><div class="col"><td>'._('Copy:').'<br>';
	if ( CAPTCHA_DIFFICULTY === 1 ) {
		$im = imagecreatetruecolor( 55, 24 );
		$bg = imagecolorallocate( $im, 0, 0, 0 );
		$fg = imagecolorallocate( $im, 255, 255, 255 );
		imagefill( $im, 0, 0, $bg );
		imagestring( $im, 5, 5, 5, $code, $fg );
		echo '<img alt="" width="55" height="24" src="data:image/gif;base64,';
	} elseif ( CAPTCHA_DIFFICULTY === 2 ) {
		$im = imagecreatetruecolor( 55, 24 );
		$bg = imagecolorallocate( $im, 0, 0, 0 );
		$fg = imagecolorallocate( $im, 255, 255, 255 );
		imagefill( $im, 0, 0, $bg );
		imagestring( $im, 5, 5, 5, $code, $fg );
		$line = imagecolorallocate( $im, 255, 255, 255 );
		for ( $i = 0; $i < 2; ++$i ) {
			imageline( $im, 0, mt_rand( 0, 24 ), 55, mt_rand( 0, 24 ), $line );
		}
		$dots = imagecolorallocate( $im, 255, 255, 255 );
		for ( $i = 0; $i < 100; ++$i ) {
			imagesetpixel( $im, mt_rand( 0, 55 ), mt_rand( 0, 24 ), $dots );
		}
		echo '<img alt="" width="55" height="24" src="data:image/gif;base64,';
	} elseif (CAPTCHA_DIFFICULTY === 3){
		$im = imagecreatetruecolor(55, 24);
		$bg = imagecolorallocatealpha($im, 0, 0, 0, 127);
		$fg = imagecolorallocate($im, 255, 255, 255);
		$cc = imagecolorallocate($im, 200, 200, 200);
		$cb = imagecolorallocatealpha($im, 0, 0, 0, 127);
		imagefill($im, 0, 0, $bg);
		$line = imagecolorallocate($im, 255, 255, 255);
		$deg = (mt_rand(0,1)*2-1)*mt_rand(10, 20);

		$background = imagecreatetruecolor(120, 80);
		imagefill($background, 0, 0, $cb);

		for ($i=0; $i<20; ++$i) {
			$char = imagecreatetruecolor(12, 16);
			imagestring($char, 5, 2, 2, $captchachars[mt_rand(0, $length)], $cc);
			$char = imagerotate($char, (mt_rand(0,1)*2-1)*mt_rand(10, 20), $cb);
			$char = imagescale($char, 24, 32);
			imagefilter($char, IMG_FILTER_SMOOTH, 0.6);
			imagecopy($background, $char, rand(0, 100), rand(0, 60), 0, 0, 24, 32);
		}

		imagestring($im, 5, 5, 5, $code, $fg);
		$im = imagescale($im, 110, 48);
		imagefilter($im, IMG_FILTER_SMOOTH, 0.5);
		imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
		$im = imagerotate($im, $deg, $bg);
		$im = imagecrop($im, array('x'=>0, 'y'=>0, 'width'=>120, 'height'=>80));
		imagecopy($background, $im, 0, 0, 0, 0, 110, 80);
		imagedestroy($im);
		$im = $background;

		for($i=0; $i<1000; ++$i){
			$c = mt_rand(100,230);
			$dots = imagecolorallocate($im, $c, $c, $c);
			imagesetpixel($im, mt_rand(0, 120), mt_rand(0, 80), $dots);
		}
		imagedestroy($char);
		echo '<img width="120" height="80" src="data:image/png;base64,';
	} else {
		$im = imagecreatetruecolor( 150, 200 );
		$bg = imagecolorallocate( $im, 0, 0, 0 );
		$fg = imagecolorallocate( $im, 255, 255, 255 );
		imagefill( $im, 0, 0, $bg );
		$chars = [];
		$x = $y = 0;
		for ( $i = 0; $i < 10; ++$i ) {
			$found = false;
			while ( ! $found ) {
				$x = mt_rand( 10, 140 );
				$y = mt_rand( 10, 180 );
				$found = true;
				foreach ( $chars as $char ) {
					if ( $char[ 'x' ] >= $x && ( $char[ 'x' ] - $x ) < 25 ) {
						$found = false;
					} elseif ( $char[ 'x' ] < $x && ( $x - $char[ 'x' ] ) < 25 ) {
						$found = false;
					}
					if ( ! $found ) {
						if ( $char[ 'y' ] >= $y && ( $char[ 'y' ] - $y ) < 25 ) {
							break;
						} elseif ( $char[ 'y' ] < $y && ( $y - $char[ 'y' ] ) < 25 ) {
							break;
						} else {
							$found = true;
						}
					}
				}
			}
			$chars[] = [ 'x', 'y' ];
			$chars[ $i ][ 'x' ] = $x;
			$chars[ $i ][ 'y' ] = $y;
			if ( $i < 5 ) {
				imagechar( $im, 5, $chars[ $i ][ 'x' ], $chars[ $i ][ 'y' ], $captchachars[ mt_rand( 0, $length ) ], $fg );
			} else {
				imagechar( $im, 5, $chars[ $i ][ 'x' ], $chars[ $i ][ 'y' ], $code[ $i - 5 ], $fg );
			}
		}
		$follow = imagecolorallocate( $im, 200, 0, 0 );
		imagearc( $im, $chars[ 5 ][ 'x' ] + 4, $chars[ 5 ][ 'y' ] + 8, 16, 16, 0, 360, $follow );
		for ( $i = 5; $i < 9; ++$i ) {
			imageline( $im, $chars[ $i ][ 'x' ] + 4, $chars[ $i ][ 'y' ] + 8, $chars[ $i + 1 ][ 'x' ] + 4, $chars[ $i + 1 ][ 'y' ] + 8, $follow );
		}
		$line = imagecolorallocate( $im, 255, 255, 255 );
		for ( $i = 0; $i < 5; ++$i ) {
			imageline( $im, 0, mt_rand( 0, 200 ), 150, mt_rand( 0, 200 ), $line );
		}
		$dots = imagecolorallocate( $im, 255, 255, 255 );
		for ( $i = 0; $i < 1000; ++$i ) {
			imagesetpixel( $im, mt_rand( 0, 150 ), mt_rand( 0, 200 ), $dots );
		}
		echo '<img alt="" width="150" height="200" src="data:image/gif;base64,';
	}
	ob_start();
	imagegif( $im );
	imagedestroy( $im );
	echo base64_encode( ob_get_clean() ) . '">';
	echo '</div><div class="col"><input type="hidden" name="challenge" value="' . $randid . '"><input type="text" name="captcha" size="15" autocomplete="off" required></div></div>';
}

function check_captcha( string $challenge, string $captcha_code ): bool
{
	$db = get_db_instance();
	if ( CAPTCHA_DIFFICULTY > 0 ) {
		if ( empty( $challenge ) ) {
			return false;
		}
		$code = '';
		$stmt = $db->prepare( 'SELECT code FROM captcha WHERE id=?;' );
		$stmt->execute( [ $challenge ] );
		$stmt->bindColumn( 1, $code );
		if ( ! $stmt->fetch( PDO::FETCH_BOUND ) ) {
			return false;
		}
		$time = time();
		$stmt = $db->prepare( 'DELETE FROM captcha WHERE id=? OR time < ?;' );
		$stmt->execute( [ $challenge, $time - 600 ] );
		if ( $captcha_code !== $code ) {
			if ( CAPTCHA_DIFFICULTY !== 3 || strrev( $captcha_code ) !== $code ) {
				return false;
			}
		}
	}
	return true;
}

function validate_email_list( array $targets, string &$msg = '' ): string
{
	$alias_goto = '';
	$targets = array_unique( $targets );
	foreach ( $targets as $email ) {
		$validator = new EmailValidator();
		if ( $validator->isValid( $email, new NoRFCWarningsValidation() ) ) {
			$alias_goto .= ",$email";
		} else {
			$msg .= '<div class="red" role="alert">'.sprintf(htmlspecialchars(_('Oops, the email "%s" doesn\' look like a valid email address and thus wasn\'t added to the forwarding list.')), htmlspecialchars( $email ) ) . '</div>';
		}
	}
	return ltrim( $alias_goto, ',' );
}

function check_domain_access( string &$email, string &$msg = '' ): bool
{
	if ( ! $_SESSION[ 'email_admin_superadmin' ] ) {
		$db = get_db_instance();
		$parser = new EmailParser( new EmailLexer() );
		$parser->parse( $email );
		$domain = $parser->getDomainPart();
		$stmt = $db->prepare( 'SELECT target_domain FROM alias_domain WHERE alias_domain = ? AND active=1;' );
		$stmt->execute( [ $domain ] );
		if ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$domain = $tmp[ 'target_domain' ];
			$email = preg_replace( '~@[^@+]$~iu', "@$domain", $email );
		}
		$managed_domains = [];
		$stmt = $db->prepare( 'SELECT domain FROM domain_admins WHERE username = ?;' );
		$stmt->execute( [ $_SESSION[ 'email_admin_user' ] ] );
		while ( $tmp = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$managed_domains [] = $tmp[ 'domain' ];
		}
		if ( ! in_array( $domain, $managed_domains, true ) ) {
			$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('You are not allowed to manage this domain.')).'</div>';
			return false;
		}
	}
	return true;
}

function check_email_valid( string $email, string &$msg = '' ): bool
{
	$validator = new EmailValidator();
	if ( ! $validator->isValid( $email, new NoRFCWarningsValidation() ) ) {
		$msg .= '<div class="red" role="alert">'.htmlspecialchars(_('Invalid email address.')).'</div>';
		return false;
	}
	return true;
}

function alt_links(): void
{
	global $language;
	foreach(LANGUAGES as $lang => $data) {
		if($lang === $language){
			continue;
		}
		echo '<link rel="alternate" href="?lang='.$lang.'" hreflang="'.$lang.'" />';
		echo '<meta property="og:locale:alternate" content="'.$data['locale'].'">';
	}
}
