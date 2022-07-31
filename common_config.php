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
const CAPTCHA_DIFFICULTY = 1; // captcha difficulty from 0 to 3
const RESERVED_USERNAMES = ['about', 'abuse', 'admin', 'administrator', 'billing', 'contact', 'daemon', 'ftp', 'help', 'hostmaster', 'info', 'legal', 'list', 'list-request', 'lists', 'maildaemon', 'mailerdaemon', 'mailer-daemon', 'marketing', 'media', 'news', 'newsletter', 'nobody', 'noc', 'noreply', 'no-reply', 'notification', 'notifications', 'notify', 'offer', 'offers', 'office', 'official', 'order', 'orders', 'phish', 'phishing', 'postmaster', 'root', 'sale', 'sales', 'security', 'service', 'services', 'shop', 'shopping', 'spam', 'staff', 'support', 'survey', 'system', 'team', 'teams', 'unsbubscribe', 'uucp', 'usenet', 'user', 'username', 'users', 'web', 'webmail', 'webmaster', 'webmasters', 'welcome', 'www']; // list of reserved usernames that can mot be used on public registration

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
		die( 'No Connection to MySQL database!' );
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
	echo '<div class="row"><div class="col"><td>Copy:<br>';
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
			$msg .= '<div class="red" role="alert">Oops, the email "' . htmlspecialchars( $email ) . '" doesn\' look like a valid email address and thus wasn\'t added to the forwarding list.</div>';
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
			$msg .= '<div class="red" role="alert">You are not allowed to manage this domain.</div>';
			return false;
		}
	}
	return true;
}

function check_email_valid( string $email, string &$msg = '' ): bool
{
	$validator = new EmailValidator();
	if ( ! $validator->isValid( $email, new NoRFCWarningsValidation() ) ) {
		$msg .= '<div class="red" role="alert">Invalid email address.</div>';
		return false;
	}
	return true;
}
