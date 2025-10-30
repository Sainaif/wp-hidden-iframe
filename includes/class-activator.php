<?php
/**
 * Plugin Activator Class
 *
 * Handles all plugin activation tasks including database table creation,
 * default options setup, and version migrations.
 *
 * @package    Secure_Embed
 * @since      1.0.0
 */

namespace Secure_Embed;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 *
 * Fired during plugin activation.
 */
class Activator {

	/**
	 * Plugin activation handler
	 *
	 * Creates database table, sets up default options, and handles migrations
	 * from previous versions. This method is called by the activation hook.
	 *
	 * @since      1.0.0
	 */
	public static function activate() {
		self::create_database_table();
		self::set_default_options();
		self::handle_version_migration();
	}

	/**
	 * Create or update the database table
	 *
	 * Uses dbDelta() to create the table if it doesn't exist or update it
	 * if the schema has changed. Maintains backward compatibility with
	 * existing installations.
	 *
	 * @since      1.0.0
	 */
	private static function create_database_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'secure_embed_videos';
		$charset_collate = $wpdb->get_charset_collate();

		// SQL for creating the table
		// Schema matches original version for backward compatibility
		$sql = "CREATE TABLE $table_name (
			id INT NOT NULL AUTO_INCREMENT,
			db_name VARCHAR(255) NOT NULL,
			embed_name VARCHAR(255) NOT NULL,
			link TEXT NOT NULL,
			unique_id VARCHAR(100) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_id (unique_id),
			KEY db_name (db_name(191)),
			KEY embed_name (embed_name(191))
		) $charset_collate;";

		// Include the WordPress upgrade functions
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create or update the table
		dbDelta( $sql );

		// Log any dbDelta errors for debugging
		if ( ! empty( $wpdb->last_error ) ) {
			error_log( 'Secure Embed Database Error: ' . $wpdb->last_error );
		}
	}

	/**
	 * Set default plugin options
	 *
	 * Initializes plugin options with sensible defaults if they don't exist.
	 * Preserves existing options during upgrades.
	 *
	 * @since      1.0.0
	 */
	private static function set_default_options() {
		// Default options with their values
		$default_options = array(
			'secure_embed_block_devtools'          => 'off',
			'secure_embed_block_devtools_url'      => site_url( '/404' ),
			'secure_embed_input_width'             => 400,
			'secure_embed_show_edit_delete'        => 'on',
			'secure_embed_pagination_per_page'     => 20,
			'secure_embed_default_sort'            => 'ASC',
		);

		// Set each option if it doesn't already exist
		foreach ( $default_options as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				update_option( $option_name, $default_value );
			}
		}
	}

	/**
	 * Handle version migrations
	 *
	 * Performs any necessary data migrations when upgrading from older versions.
	 * Tracks the current version to determine what migrations are needed.
	 *
	 * @since      1.0.0
	 */
	private static function handle_version_migration() {
		$current_version = get_option( 'secure_embed_version', '0.0.0' );

		// If upgrading from version before 2.0.0
		if ( version_compare( $current_version, '2.0.0', '<' ) ) {
			self::migrate_from_pre_2_0();
		}

		// Update the version number
		update_option( 'secure_embed_version', SECURE_EMBED_VERSION );
	}

	/**
	 * Migrate from pre-2.0 versions
	 *
	 * Handles any data structure changes or cleanup needed when upgrading
	 * from versions before 2.0.0.
	 *
	 * @since      1.0.0
	 */
	private static function migrate_from_pre_2_0() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'secure_embed_videos';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			// Table doesn't exist, nothing to migrate
			return;
		}

		// Add indexes if they don't exist (improves search performance)
		$indexes = $wpdb->get_results( "SHOW INDEX FROM $table_name" );
		$index_names = array_column( $indexes, 'Key_name' );

		// Add db_name index if missing
		if ( ! in_array( 'db_name', $index_names, true ) ) {
			$wpdb->query( "ALTER TABLE $table_name ADD INDEX db_name (db_name(191))" );
		}

		// Add embed_name index if missing
		if ( ! in_array( 'embed_name', $index_names, true ) ) {
			$wpdb->query( "ALTER TABLE $table_name ADD INDEX embed_name (embed_name(191))" );
		}

		// Log successful migration
		error_log( 'Secure Embed: Successfully migrated from pre-2.0 version' );
	}

	/**
	 * Get the database table name
	 *
	 * Helper method to get the full table name with prefix.
	 *
	 * @since      1.0.0
	 * @return   string    The full table name.
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'secure_embed_videos';
	}
}
