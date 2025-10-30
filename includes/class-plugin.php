<?php
/**
 * Main Plugin Class
 *
 * The core plugin class that orchestrates all components and serves as a
 * dependency injection container. Uses singleton pattern to ensure only
 * one instance exists throughout the WordPress request lifecycle.
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
 * Class Plugin
 *
 * Main plugin orchestrator that initializes and coordinates all components.
 */
class Plugin {

	/**
	 * Single instance of the class (Singleton pattern)
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Database operations handler
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * AJAX request handler
	 *
	 * @var Ajax_Handler
	 */
	private $ajax_handler;

	/**
	 * Admin menu manager
	 *
	 * @var Admin_Menu
	 */
	private $admin_menu;

	/**
	 * Get singleton instance
	 *
	 * @return Plugin The single instance of the class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Private to enforce singleton
	 *
	 * Initializes the plugin by loading dependencies and registering hooks.
	 */
	private function __construct() {
		$this->init_components();
		$this->register_hooks();
	}

	/**
	 * Initialize plugin components
	 *
	 * Instantiates all the main classes needed for the plugin to function.
	 * Acts as a simple dependency injection container.
	 */
	private function init_components() {
		// Initialize database handler
		$this->database = new Database();

		// Initialize AJAX handler with database dependency
		$this->ajax_handler = new Ajax_Handler( $this->database );

		// Initialize admin menu (only in admin context)
		if ( is_admin() ) {
			$this->admin_menu = new Admin_Menu( $this->database );
		}
	}

	/**
	 * Register WordPress hooks
	 *
	 * Sets up all the hooks that the plugin needs to function.
	 * Separates concerns by having each component register its own hooks.
	 */
	private function register_hooks() {
		// Register AJAX hooks
		$this->ajax_handler->register_hooks();

		// Register admin hooks (only in admin)
		if ( is_admin() && $this->admin_menu ) {
			$this->admin_menu->register_hooks();
		}

		// Register frontend hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
	}

	/**
	 * Enqueue frontend scripts
	 *
	 * Loads the JavaScript needed for video toggling and DevTools blocking
	 * on the frontend. Only loads what's necessary based on settings.
	 */
	public function enqueue_frontend_scripts() {
		// Register main frontend script (video toggler)
		wp_register_script(
			'secure-embed-frontend',
			SECURE_EMBED_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			SECURE_EMBED_VERSION,
			true
		);

		// Localize script with AJAX URL and nonce
		wp_localize_script(
			'secure-embed-frontend',
			'secureEmbedData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'secure_embed_fetch_link' ),
			)
		);

		// Enqueue the script
		wp_enqueue_script( 'secure-embed-frontend' );

		// Conditionally load DevTools blocker if enabled
		$devtools_enabled = get_option( 'secure_embed_block_devtools', 'off' );
		if ( 'on' === $devtools_enabled ) {
			// Get redirect URL
			$redirect_url = get_option( 'secure_embed_block_devtools_url', site_url( '/404' ) );

			// Read the devtools blocker file
			$devtools_file = SECURE_EMBED_PLUGIN_DIR . 'assets/js/devtools-blocker.js';

			if ( file_exists( $devtools_file ) ) {
				$devtools_js = file_get_contents( $devtools_file );

				// Replace BLOCK_URL placeholder with actual redirect URL
				$devtools_js = str_replace( 'BLOCK_URL', esc_url( $redirect_url ), $devtools_js );

				// Add as inline script
				wp_add_inline_script( 'secure-embed-frontend', $devtools_js, 'after' );
			}
		}
	}

	/**
	 * Get the database handler
	 *
	 * @return Database The database operations handler.
	 */
	public function get_database() {
		return $this->database;
	}

	/**
	 * Get the AJAX handler
	 *
	 * @return Ajax_Handler The AJAX request handler.
	 */
	public function get_ajax_handler() {
		return $this->ajax_handler;
	}

	/**
	 * Get the admin menu manager
	 *
	 * @return Admin_Menu|null The admin menu manager, or null if not in admin context.
	 */
	public function get_admin_menu() {
		return $this->admin_menu;
	}

	/**
	 * Prevent cloning of the instance
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the instance
	 */
	public function __wakeup() {
		throw new \Exception( __( 'Cannot unserialize singleton', 'secure-hash-embed-manager' ) );
	}
}
