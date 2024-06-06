<?php
const ADMIN_USER='admin';
const ADMIN_PASS='YOUR_PASSWORD';

require_once __DIR__ . '/../common_config.php';
$db = get_db_instance();
$hash = password_hash( ADMIN_PASS, PASSWORD_ARGON2ID );
$stmt = $db->prepare( 'INSERT INTO admin (password_hash_type, password, superadmin, username, created, modified) VALUES ("{ARGON2ID}", ?, 1, ?, NOW(), NOW());' );
$stmt->execute( [ $hash, ADMIN_USER ] );
$stmt = $db->prepare( 'INSERT IGNORE INTO domain (domain, created, modified) VALUES (?, NOW(), NOW())' );
$stmt->execute( [ PRIMARY_DOMAIN ] );