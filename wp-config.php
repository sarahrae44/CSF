<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'csf');

/** MySQL database username */
define('DB_USER', 'csf');

/** MySQL database password */
define('DB_PASSWORD', 'csfarms');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

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
define('AUTH_KEY',         'XB1&&T.PT1JDURaka|ljzmBO,Fv>7`+v%U,(qlR>8UZ[x0?pn5[G~1>AkRVRKM>l');
define('SECURE_AUTH_KEY',  '7TdJl/Ux@[u5>wJ^TA-l~09*jZ4UL>7MG&VFH4aR}?Hel,Cq(Yfnx{nO_by*&.jH');
define('LOGGED_IN_KEY',    '11!!j:!%smChs&+?L~&Bc{&sZa5GSQ]fe#&4:q0r+KW3!XfK?)OGMFig8-+UpmFt');
define('NONCE_KEY',        '5ZkDVsVgZ#T]B;p[^}`0S{mj:lH`0Wh;?VWGfTyr=]A?)A`n;!bWhfa#=BX/|5g@');
define('AUTH_SALT',        'i?ppHt-hTy`CrSok1pNfi4RAn*3PyVK6CAqghCpR]0<*rF&61mrJr;P,7D51&Yal');
define('SECURE_AUTH_SALT', 'm)q s*UyJ7 m<se/S83ZJ/fV?QLM_ |TM$(2lm&A=FX]w4sy%x>f#,T:A]bi`1QW');
define('LOGGED_IN_SALT',   '?zI|~GjoGN/nBZ2cPsw*IR(;=fkL2^RV%]Jw#`3}/6D|>10ax^lzMwDDV}LES^hX');
define('NONCE_SALT',       'OP9,Qaba9NQ,&Fh},8od1H*!9<OpC!{3o(##2Qq*ef*muM^d6pU`VVq:-2)qd(du');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
