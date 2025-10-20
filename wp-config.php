<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'task' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '~4BShbO%sE;!+JMnp/c3SS`M?fEz~!@_Du$Nt1ed5E;v))ZeehQ8;qQ0~9FplV~&' );
define( 'SECURE_AUTH_KEY',  'IQ5<ii1k&Bn6wwMblu8#zWW-0jWh& ?YGS^l+_b%x|R@ZnL%80tBuoj6EEo~=$ v' );
define( 'LOGGED_IN_KEY',    'mFcvr<]t&c;PL|^q~rgwW=.QVaLUP=[]{L]e#$xG/q?;:1!@yOKp7e-tE<NS<zLA' );
define( 'NONCE_KEY',        'X?VX~lh9b&3!+G:TzDmr/JRQ2JgYrSr^BT|ED...fs<h0q>9G4bBs*<-Z(?Z^N&:' );
define( 'AUTH_SALT',        'yWr>j1|wtg)PaIi@sj@7)Al=CzTw;,}5N,QL|CSVtnNN%XM-PW`R?`Hr)cr)DR^{' );
define( 'SECURE_AUTH_SALT', 'm#W5>Fo<=7~/lr~WqBR?f4o7HaGK=(}xC]E^Y|%.d31(Pr.f3n[T2f`LJbBrK(:v' );
define( 'LOGGED_IN_SALT',   'QCe_k)k$5]5pr1B|b./We}qAUgWmAh_#1invf{[))FVK#_heeJ)fMzjOjF+Ra2I9' );
define( 'NONCE_SALT',       'OmK&y^3eABM&(+VY^~F%l2Bk4B_kO0]:4<Gj(OPGX`i3>$E!t7(S8X./rzo^@$zm' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', true );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
