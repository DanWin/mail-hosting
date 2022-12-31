<?php
if(!extension_loaded('gettext')){
	die('The gettext extension of PHP is required. Please install it first.' . PHP_EOL);
}
require('common_config.php');
foreach(['pdo_mysql', 'mbstring', 'pcre', 'gnupg', 'intl'] as $required_extension) {
	if ( ! extension_loaded( $required_extension ) ) {
		die( sprintf( _( 'The %s extension of PHP is required. Please install it first.' ), $required_extension ) . PHP_EOL );
	}
}
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
}catch(PDOException){
	try{
		//Attempt to create database
		$db=new PDO('mysql:host=' . DBHOST . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
		if(false!==$db->exec('CREATE DATABASE ' . DBNAME)){
			$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
		}else{
			die( _('No Connection to MySQL database!') . PHP_EOL);
		}
	}catch(PDOException){
		die( _('No Connection to MySQL database!') . PHP_EOL);
	}
}
try{
	$stmt=$db->query("SELECT value FROM settings WHERE setting='version';");
	$version=$stmt->fetch(PDO::FETCH_NUM)[0];
	try {
		$db->beginTransaction();
		$stmt=$db->prepare("UPDATE settings SET value=? WHERE setting='version';");
		$stmt->execute([DBVERSION]);
		$db->commit();
		if($version < DBVERSION){
			echo _('Database has successfully been updated.') . PHP_EOL;
		} else {
			echo _('Database is already up-to-date.') . PHP_EOL;
		}
	} catch(PDOException $e){
		echo _('Error updating database:') . PHP_EOL;
		echo $e->getMessage() . PHP_EOL;
		$db->rollBack();
	}
} catch(PDOException){
	//create tables
	try {
		$db->exec("CREATE TABLE `admin` (`username` varchar(255) CHARACTER SET utf8mb4 NOT NULL, `password` varchar(255) CHARACTER SET utf8mb4 NOT NULL, `superadmin` tinyint(1) NOT NULL DEFAULT 0, `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `modified` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `active` tinyint(1) NOT NULL DEFAULT 1, `password_hash_type` varchar(20) CHARACTER SET utf8mb4 NOT NULL DEFAULT '{ARGON2ID}', PRIMARY KEY (`username`), KEY `active` (`active`)) DEFAULT CHARSET=utf8mb4;");
		$db->exec("CREATE TABLE `domain` (`domain` varchar(255) CHARACTER SET utf8mb4 NOT NULL, `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `modified` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `active` tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (`domain`), KEY `active` (`active`)) DEFAULT CHARSET=utf8mb4;");
		$db->exec("CREATE TABLE `alias` (`address` varchar(255) CHARACTER SET utf8mb4 NOT NULL, `goto` text CHARACTER SET utf8mb4 NOT NULL, `domain` varchar(255) CHARACTER SET utf8mb4 NOT NULL, `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `modified` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `active` tinyint(1) NOT NULL DEFAULT 1, `enforce_tls_in` tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (`address`), KEY `domain` (`domain`), KEY `active` (`active`), CONSTRAINT `alias_ibfk_1` FOREIGN KEY (`domain`) REFERENCES `domain` (`domain`) ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4;");
		$db->exec("CREATE TABLE `alias_domain` (`alias_domain` varchar(255) CHARACTER SET utf8mb4 NOT NULL DEFAULT '', `target_domain` varchar(255) CHARACTER SET utf8mb4 NOT NULL DEFAULT '', `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `modified` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `active` tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (`alias_domain`), KEY `active` (`active`), KEY `target_domain` (`target_domain`)) DEFAULT CHARSET=utf8mb4;");
		$db->exec("CREATE TABLE `captcha` (`id` int(11) NOT NULL AUTO_INCREMENT, `time` int(11) NOT NULL, `code` char(5) COLLATE utf8mb4_bin NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$db->exec("CREATE TABLE `domain_admins` (`username` varchar(255) CHARACTER SET utf8mb4 NOT NULL, `domain` varchar(255) CHARACTER SET utf8mb4 NOT NULL, `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00', `active` tinyint(1) NOT NULL DEFAULT 1, `id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`), KEY `username` (`username`), KEY `active` (`active`), KEY `domain` (`domain`), CONSTRAINT `domain_admins_ibfk_1` FOREIGN KEY (`domain`) REFERENCES `domain` (`domain`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `domain_admins_ibfk_2` FOREIGN KEY (`username`) REFERENCES `admin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4;");
		$db->exec("CREATE TABLE `mailbox` (`username` varchar(255) NOT NULL, `password` varchar(255) NOT NULL, `quota` bigint(20) NOT NULL DEFAULT 0, `local_part` varchar(255) NOT NULL, `domain` varchar(255) NOT NULL, `created` datetime NOT NULL, `modified` datetime NOT NULL, `active` tinyint(1) NOT NULL DEFAULT 1, `password_hash_type` varchar(20) NOT NULL, `openpgpkey_wkd` char(32) NOT NULL, `pgp_key` text DEFAULT NULL, `pgp_verified` tinyint(1) NOT NULL DEFAULT 0, `tfa` tinyint(1) NOT NULL DEFAULT 0, `last_login` bigint(20) unsigned DEFAULT NULL, `enforce_tls_in` tinyint(1) NOT NULL DEFAULT 1, `enforce_tls_out` tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (`username`), KEY `domain` (`domain`), KEY `active` (`active`), KEY `openpgpkey_wkd` (`openpgpkey_wkd`), CONSTRAINT `mailbox_ibfk_2` FOREIGN KEY (`domain`) REFERENCES `domain` (`domain`) ON UPDATE CASCADE) DEFAULT CHARSET=utf8mb4;");
		$db->exec('CREATE TABLE settings (setting varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL PRIMARY KEY, value text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;');
		$stmt=$db->prepare("INSERT INTO settings (setting, value) VALUES ('version', ?);");
		$stmt->execute([DBVERSION]);
		echo _('Database has successfully been set up.') . PHP_EOL;
	} catch(PDOException $e){
		echo _('Error setting up database:') . PHP_EOL;
		echo $e->getMessage() . PHP_EOL;
	}
}
