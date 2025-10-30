<?php
/**
 * Plugin Name: Secure Hash & Embed Manager
 * Plugin URI: https://github.com/Sainaif/wp-hidden-iframe
 * Description: Manages secure video links with hashed IDs, hides real URLs, includes DevTools blocking, sorting, searching, and multi-language support.
 * Version: 1.0.0
 * Author: Sainaif
 * Author URI: https://github.com/Sainaif
 * Text Domain: secure-hash-embed-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * License: MIT
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants
 */
define( 'SECURE_EMBED_VERSION', '1.0.0' );
define( 'SECURE_EMBED_PLUGIN_FILE', __FILE__ );
define( 'SECURE_EMBED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SECURE_EMBED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SECURE_EMBED_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 Autoloader
 *
 * Automatically loads classes from the includes directory.
 * Class names are converted to file paths following PSR-4 standards.
 *
 * Example: Secure_Embed\Database -> includes/class-database.php
 */
spl_autoload_register( function ( $class ) {
	// Project-specific namespace prefix
	$prefix = 'Secure_Embed\\';

	// Base directory for the namespace prefix
	$base_dir = SECURE_EMBED_PLUGIN_DIR . 'includes/';

	// Does the class use the namespace prefix?
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		// No, move to the next registered autoloader
		return;
	}

	// Get the relative class name
	$relative_class = substr( $class, $len );

	// Replace namespace separators with directory separators
	// Remove 'Admin\' prefix if present for admin classes
	$relative_class = str_replace( 'Admin\\', '', $relative_class );

	// Convert class name to file name format (Class_Name -> class-name)
	$class_parts = explode( '\\', $relative_class );
	$class_name = array_pop( $class_parts );
	$class_file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

	// Check if it's an admin class
	if ( strpos( $relative_class, 'Admin\\' ) === 0 ||
	     in_array( $class_name, array( 'Admin_Menu', 'Videos_Page', 'Database_Page', 'Settings_Page' ) ) ) {
		$file = $base_dir . 'admin/' . $class_file;
	} else {
		$file = $base_dir . $class_file;
	}

	// If the file exists, require it
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Initialize the plugin
 *
 * Loads text domain for translations and initializes the main plugin class.
 */
function secure_embed_init() {
	// Load plugin text domain for translations
	load_plugin_textdomain(
		'secure-hash-embed-manager',
		false,
		dirname( SECURE_EMBED_PLUGIN_BASENAME ) . '/languages'
	);

	// Initialize the main plugin class
	if ( class_exists( 'Secure_Embed\Plugin' ) ) {
		Secure_Embed\Plugin::get_instance();
	}
}
add_action( 'plugins_loaded', 'secure_embed_init' );

/**
 * Plugin activation hook
 *
 * Runs when the plugin is activated. Handles database table creation,
 * default options setup, and any necessary migrations.
 */
function secure_embed_activate() {
	require_once SECURE_EMBED_PLUGIN_DIR . 'includes/class-activator.php';
	Secure_Embed\Activator::activate();
}
register_activation_hook( __FILE__, 'secure_embed_activate' );

/**
 * Plugin deactivation hook
 *
 * Runs when the plugin is deactivated. Clean up temporary data if needed.
 */
function secure_embed_deactivate() {
	// Clear any temporary caches or scheduled events if needed
	// We don't delete options or database table on deactivation
}
register_deactivation_hook( __FILE__, 'secure_embed_deactivate' );
