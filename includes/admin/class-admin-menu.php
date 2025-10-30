<?php
/**
 * Admin Menu Class
 *
 * Registers admin menu pages and coordinates page rendering.
 *
 * @package    Secure_Embed
 * @subpackage Secure_Embed/Admin
 * @since      1.0.0
 */

namespace Secure_Embed;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Menu
 *
 * Manages WordPress admin menu and page registration.
 */
class Admin_Menu {

	/**
	 * Database handler instance
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Videos page instance
	 *
	 * @var Videos_Page
	 */
	private $videos_page;

	/**
	 * Database management page instance
	 *
	 * @var Database_Page
	 */
	private $database_page;

	/**
	 * Settings page instance
	 *
	 * @var Settings_Page
	 */
	private $settings_page;

	/**
	 * Constructor
	 *
	 * @since      1.0.0
	 * @param    Database  $database    Database handler instance.
	 */
	public function __construct( $database ) {
		$this->database = $database;

		// Initialize page instances
		$this->videos_page   = new Videos_Page( $this->database );
		$this->database_page = new Database_Page( $this->database );
		$this->settings_page = new Settings_Page();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @since      1.0.0
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add admin menu and submenus
	 *
	 * Creates the main menu item and three submenu pages.
	 * Uses the same menu slugs as the original version for backward compatibility.
	 *
	 * @since      1.0.0
	 */
	public function add_admin_menu() {
		// Main menu page - Video Manager
		// Note: WordPress automatically creates a submenu item with the same slug as the parent
		add_menu_page(
			__( 'Secure Embed Manager', 'secure-hash-embed-manager' ),
			__( 'Secure Embed Manager', 'secure-hash-embed-manager' ),
			'manage_options',
			'secure-embed',
			array( $this->videos_page, 'render' ),
			'dashicons-admin-links',
			20
		);

		// Submenu: Database Management
		add_submenu_page(
			'secure-embed',
			__( 'Database Management', 'secure-hash-embed-manager' ),
			__( 'Database Management', 'secure-hash-embed-manager' ),
			'manage_options',
			'secure-embed-db',
			array( $this->database_page, 'render' )
		);

		// Submenu: Plugin Settings
		add_submenu_page(
			'secure-embed',
			__( 'Plugin Settings', 'secure-hash-embed-manager' ),
			__( 'Settings', 'secure-hash-embed-manager' ),
			'manage_options',
			'secure-embed-settings',
			array( $this->settings_page, 'render' )
		);
	}

	/**
	 * Enqueue admin assets (CSS and JavaScript)
	 *
	 * Loads admin stylesheets and scripts only on plugin pages.
	 *
	 * @since      1.0.0
	 * @param    string  $hook    Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our plugin pages
		$plugin_pages = array(
			'toplevel_page_secure-embed',
			'secure-embed_page_secure-embed-db',
			'secure-embed_page_secure-embed-settings',
		);

		if ( ! in_array( $hook, $plugin_pages, true ) ) {
			return;
		}

		// Enqueue admin CSS
		wp_enqueue_style(
			'secure-embed-admin',
			SECURE_EMBED_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SECURE_EMBED_VERSION
		);

		// Enqueue admin JavaScript
		wp_enqueue_script(
			'secure-embed-admin',
			SECURE_EMBED_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			SECURE_EMBED_VERSION,
			true
		);

		// Localize script with data for AJAX and translations
		wp_localize_script(
			'secure-embed-admin',
			'secureEmbedAdmin',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'secure_embed_admin_ajax' ),
				'strings'           => array(
					'confirmDelete'       => __( 'Are you sure you want to delete this video?', 'secure-hash-embed-manager' ),
					'confirmReset'        => __( 'This will permanently delete all videos. Are you sure?', 'secure-hash-embed-manager' ),
					'confirmRestore'      => __( 'This will replace all existing videos. Continue?', 'secure-hash-embed-manager' ),
					'copiedToClipboard'   => __( 'Embed code copied to clipboard!', 'secure-hash-embed-manager' ),
					'copyFailed'          => __( 'Failed to copy. Please try manually selecting the text.', 'secure-hash-embed-manager' ),
					'pleaseWait'          => __( 'Please wait...', 'secure-hash-embed-manager' ),
					'error'               => __( 'An error occurred. Please try again.', 'secure-hash-embed-manager' ),
					'fillAllFields'       => __( 'Please fill out all fields.', 'secure-hash-embed-manager' ),
					'invalidUrl'          => __( 'Please enter a valid URL.', 'secure-hash-embed-manager' ),
				),
			)
		);
	}
}
