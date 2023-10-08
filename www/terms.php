<?php
include_once('../common_config.php');
global $language, $dir, $locale;
?>
<!DOCTYPE html><html lang="<?php echo $language; ?>" dir="<?php echo $dir; ?>"><head>
<title><?php echo htmlspecialchars(_('E-Mail and XMPP - Terms of Service')); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?php echo htmlspecialchars(_('Terms of Service for E-Mail and XMPP accounts')); ?>">
<link rel="canonical" href="<?php echo CANONICAL_URL; ?>terms.php">
<link rel="alternate" href="<?php echo CANONICAL_URL; ?>terms.php" hreflang="x-default">
<?php alt_links(); ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo htmlspecialchars(_('E-Mail and XMPP - Terms of Service')); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars(_('Terms of Service for E-Mail and XMPP accounts')); ?>">
<meta property="og:url" content="<?php echo CANONICAL_URL; ?>terms.php">
<meta property="og:locale" content="<?php echo $locale; ?>">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"WebPage","name":"<?php echo htmlspecialchars(_('E-Mail and XMPP - Terms of Service')); ?>", "description": "<?php echo htmlspecialchars(_('Terms of Service for E-Mail and XMPP accounts')); ?>"}</script>
</head><body>
<main>
<p><a href="<?php echo ROOT_URL; ?>"><?php echo htmlspecialchars(_('Info')); ?></a> | <a href="<?php echo ROOT_URL; ?>register.php"><?php echo htmlspecialchars(_('Register')); ?></a> | <a href="<?php echo ROOT_URL; ?>manage_account.php"><?php echo htmlspecialchars(_('Manage account')); ?></a> | <a href="<?php echo ROOT_URL; ?>squirrelmail/src/login.php" target="_blank"><?php echo htmlspecialchars(_('SquirrelMail')); ?></a> | <a href="<?php echo ROOT_URL; ?>snappymail/" target="_blank"><?php echo htmlspecialchars(_('SnappyMail')); ?></a> | <a href="<?php echo WEB_XMPP_URL; ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(_('Web-XMPP')); ?></a></p>
<ol>
<li><?php echo htmlspecialchars(_('I provide this service as is. I do not offer any uptime guarantee.')); ?></li>
<li><?php echo htmlspecialchars(_('Inactive accounts get automatically deleted after one year of inactivity.')); ?></li>
<li><?php echo htmlspecialchars(_('Spamming is not allowed, and you will be blocked if you do.')); ?></li>
<li><?php echo htmlspecialchars(_('Using your account for illegal purposes is not allowed, and you will be blocked if you do.')); ?></li>
<li><?php echo htmlspecialchars(_('If you lose your password, I will not reset it unless you can prove ownership of the account. You could do so by signing an email with the same PGP key that you use in your account.')); ?></li>
<li><?php echo htmlspecialchars(_('You are responsible for the security of your account and password.')); ?></li>
<li><?php printf(htmlspecialchars(_('Your email account only has 50MB of disk space by default. If you need more, you can %s, and I will increase it for free.')), '<a href="'.CONTACT_URL.'">'.htmlspecialchars(_('contact me')).'</a>'); ?></li>
<li><?php echo htmlspecialchars(_('The XMPP service provides message archiving and HTTP upload, which can keep your messages and files for up to 1 week. Up to 100MB of file storage is available per user.')); ?></li>
<li><?php echo htmlspecialchars(_('I reserve the right to block or delete your account without prior notice.')); ?></li>
<li><?php echo htmlspecialchars(_('I reserve the right to change these terms without prior notice.')); ?></li>
</ol>
</main>
</body></html>
