<?php
include_once('../common_config.php');
global $language, $dir, $locale;
?>
<!DOCTYPE html><html lang="<?php echo $language; ?>" dir="<?php echo $dir; ?>"><head>
<title><?php echo htmlspecialchars(_('E-Mail and XMPP')); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?php echo htmlspecialchars(_('Get a free and anonymous E-Mail address and an XMPP/Jabber account')); ?>">
<link rel="canonical" href="<?php echo CANONICAL_URL; ?>">
<link rel="alternate" href="<?php echo CANONICAL_URL; ?>" hreflang="x-default">
<?php alt_links(); ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo htmlspecialchars(_('E-Mail and XMPP')); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars(_('Get a free and anonymous E-Mail address and an XMPP/Jabber account')); ?>">
<meta property="og:url" content="<?php echo CANONICAL_URL; ?>">
<meta property="og:locale" content="<?php echo $locale; ?>">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"Service","name":"<?php echo htmlspecialchars(_('E-Mail and XMPP')); ?>", "description": "<?php echo htmlspecialchars(_('Get a free and anonymous E-Mail address and an XMPP/Jabber account')); ?>", "availableChannel": {"@type": "ServiceChannel", "serviceUrl": "<?php echo CANONICAL_URL; ?>"}, "termsOfService": "<?php echo CANONICAL_URL; ?>terms.php"}</script>
</head><body>
<main>
<p><?php echo htmlspecialchars(_('Info')); ?> | <a href="<?php echo ROOT_URL; ?>register.php"><?php echo htmlspecialchars(_('Register')); ?></a> | <a href="<?php echo ROOT_URL; ?>manage_account.php"><?php echo htmlspecialchars(_('Manage account')); ?></a> | <a href="<?php echo ROOT_URL; ?>squirrelmail/src/login.php" target="_blank"><?php echo htmlspecialchars(_('SquirrelMail')); ?></a> | <a href="<?php echo ROOT_URL; ?>snappymail/" target="_blank"><?php echo htmlspecialchars(_('SnappyMail')); ?></a> | <a href="<?php echo WEB_XMPP_URL; ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(_('Web-XMPP')); ?></a></p>
<h2><?php echo htmlspecialchars(_('What you will get')); ?></h2>
<p><?php printf(htmlspecialchars(_('You get a free anonymous E-Mail address and an XMPP/Jabber account using the same details. Your Jabber ID is user@%1$s and can be connected to directly from clearnet or via Tor hidden service (%2$s).')), CLEARNET_SERVER, ONION_SERVER); ?></p>
<?php if(DEFAULT_QUOTA > 0) { ?>
<p><?php printf(htmlspecialchars(_('You will have %1$s disk space available for your mails. If you need more space, %2$s.')), bytes_to_human_readable(DEFAULT_QUOTA), '<a href="'.CONTACT_URL.'">'.htmlspecialchars(_('contact me')).'</a>'); ?></p>
<?php } ?>
<p><?php printf(htmlspecialchars(_('Your E-Mail address will be %s')), CLEARNET_SERVER); ?></p>
<p><?php printf(htmlspecialchars(_('For privacy, please use PGP mail encryption, if you can. This prevents others from reading your mails to protect your privacy. You can %1$s or similar software for it. Once you have generated your PGP key, you can %2$s to make use of WKD automatic discovery for mail clients.')), '<a href="https://gnupg.org/download/index.html" target="_blank" rel="noopener noreferrer">'.htmlspecialchars(_('download GnuPG')).'</a>', '<a href="manage_account.php">'.htmlspecialchars(_('add it to your account')).'</a>'); ?></p>
<p><?php printf(htmlspecialchars(_('You can choose between two Web-Mail clients installed on the server. %1$s is a very old mail client which works without any JavaScript and is thus the most popular mail client among darknet users. However, it hasn\'t been under development for many years and does not support all features that mail has to offer. You may see strange attachments that should have been inlined in your email, such as PGP/MIME encrypted email messages. A more modern client is %2$s, which also supports PGP encryption within your browser and is more similar to what you may be used to from other mail services. SnappyMail requires JavaScript though, so SquirrelMail is for you if you do not trust executing JavaScript in your browser. Alternatively, you can simply use your favourite desktop mail client and configure it with the settings given below.')), '<a href="squirrelmail/src/login.php">'.htmlspecialchars(_('SquirrelMail')).'</a>', '<a href="snappymail/">'.htmlspecialchars(_('SnappyMail')).'</a>'); ?></p>
<h2><?php echo htmlspecialchars(_('E-Mail Setup')); ?></h2>
<p>
    <?php printf(htmlspecialchars(_('SMTP: %s Port 465 (SSL/TLS) or 587 (StartTLS)')), CLEARNET_SERVER); ?><br>
	<?php printf(htmlspecialchars(_('IMAP: %s Port 993 (SSL/TLS) or 143 (StartTLS)')), CLEARNET_SERVER); ?><br>
	<?php printf(htmlspecialchars(_('POP3: %s Port 995 (SSL/TLS) or 110 (StartTLS)')), CLEARNET_SERVER); ?><br>
	<?php echo htmlspecialchars(_('Authentication: PLAIN, LOGIN')); ?>
</p>
<p><?php printf(htmlspecialchars(_('You can also connect on the same ports via the Tor onion address %s, but you will have to accept an SSL certificate only valid for the clearnet domain.')), ONION_SERVER); ?></p>
<h2><?php echo htmlspecialchars(_('XMPP setup')); ?></h2>
<p><?php printf(htmlspecialchars(_('Domain: %s')), CLEARNET_SERVER); ?><br>
	<?php printf(htmlspecialchars(_('Connect server: %s (optional for torification)')), ONION_SERVER); ?><br>
	<?php printf(htmlspecialchars(_('File transfer proxy: %s')), XMPP_FILE_PROXY); ?><br>
	<?php printf(htmlspecialchars(_('BOSH URL: %s (only enable if you have to, as it is slower than directly using xmpp)')), XMPP_BOSH_URL); ?></p>
</main>
</body></html>
