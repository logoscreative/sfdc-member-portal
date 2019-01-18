<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, and ABSPATH. You can find more information by visiting
 * {@link https://codex.wordpress.org/Editing_wp-config.php Editing wp-config.php}
 * Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */


// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wordpress');

/** MySQL database username */
define('DB_USER', 'cladmin');

/** MySQL database password */
define('DB_PASSWORD', 'P4c.ce11');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'ef4f1cd65f89d52defaefeec7d9c38294ebc9aea');
define('SECURE_AUTH_KEY',  '7ac8b734a00eeedf2d6600bf644f2ace23528ae8');
define('LOGGED_IN_KEY',    '7e32dd210381484a0f9e63f523d9546886c3d344');
define('NONCE_KEY',        '096bbcb33b952942ac8387b200a63dbadb2b715f');
define('AUTH_SALT',        '194b6078d270f96ca249165be0c7c634fb9ff07e');
define('SECURE_AUTH_SALT', '314616d11ac51ad3e24c77e7825c094ca595d599');
define('LOGGED_IN_SALT',   'd0f3b09b4e4edd53991c8b793ef87612aab8551d');
define('NONCE_SALT',       'ce8a0703f9860a61032f9761f4159d9df8913a04');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

// If we're behind a proxy server and using HTTPS, we need to alert Wordpress of that fact
// see also http://codex.wordpress.org/Administration_Over_SSL#Using_a_Reverse_Proxy
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
	$_SERVER['HTTPS'] = 'on';
}

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');

/* Added by DW for SF integration */
define("USERNAME", "api@focus-ga.org.Partial");
define("PASSWORD", "xhYHMa7RLMvDTFH");
define("SECURITY_TOKEN", "8ofo544KVNo3cxsY8qCf0koA");