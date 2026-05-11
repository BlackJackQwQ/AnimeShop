<?php
/**
 * The base configuration for WordPress for InfinityFree
 */

// Dynamic URL: Auto-detects your InfinityFree URL
if ( isset( $_SERVER['HTTP_HOST'] ) ) {
    $scheme = ( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
    define( 'WP_HOME', $scheme . '://' . $_SERVER['HTTP_HOST'] );
    define( 'WP_SITEURL', $scheme . '://' . $_SERVER['HTTP_HOST'] );
}

// ** MySQL settings - Get these from your InfinityFree Control Panel ** //
define( 'DB_NAME', 'if0_41893513_animeshop' ); 
define( 'DB_USER', 'if0_41893513' );
define( 'DB_PASSWORD', 'BlackJackQwQ' );
define( 'DB_HOST', 'sql200.infinityfree.com' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wp_';

define( 'WP_DEBUG', false );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
