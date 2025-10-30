<?php
/**
 * AJAX Handler Class
 *
 * Handles all AJAX requests for the plugin, including secure video URL fetching.
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
 * Class Ajax_Handler
 *
 * Processes AJAX requests with proper security checks.
 */
class Ajax_Handler {

	/**
	 * Database handler instance
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Constructor
	 *
	 * @since      1.0.0
	 * @param    Database  $database    Database handler instance.
	 */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/**
	 * Register WordPress AJAX hooks
	 *
	 * @since      1.0.0
	 */
	public function register_hooks() {
		// AJAX hook for logged-in and non-logged-in users
		// This is necessary for frontend functionality
		add_action( 'wp_ajax_secure_embed_fetch_link', array( $this, 'fetch_video_link' ) );
		add_action( 'wp_ajax_nopriv_secure_embed_fetch_link', array( $this, 'fetch_video_link' ) );
	}

	/**
	 * Fetch video link by unique ID
	 *
	 * AJAX handler that retrieves the actual video URL from the database
	 * based on the unique hashed ID. This is the core security feature that
	 * prevents direct URL exposure in HTML.
	 *
	 * @since      1.0.0
	 */
	public function fetch_video_link() {
		// Verify nonce for security
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'secure_embed_fetch_link' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed', 'secure-hash-embed-manager' ),
				),
				403
			);
			return;
		}

		// Get and validate the unique ID
		if ( ! isset( $_POST['video_id'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Video ID is required', 'secure-hash-embed-manager' ),
				),
				400
			);
			return;
		}

		$video_id = sanitize_text_field( wp_unslash( $_POST['video_id'] ) );

		// Validate ID format (should start with 'vid_')
		if ( ! preg_match( '/^vid_[a-zA-Z0-9]+$/', $video_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid video ID format', 'secure-hash-embed-manager' ),
				),
				400
			);
			return;
		}

		// Fetch video from database
		$video = $this->database->get_video_by_unique_id( $video_id );

		if ( ! $video ) {
			wp_send_json_error(
				array(
					'message' => __( 'Video not found', 'secure-hash-embed-manager' ),
				),
				404
			);
			return;
		}

		// Return the video URL
		wp_send_json_success(
			array(
				'link'       => esc_url( $video->link ),
				'embed_name' => esc_html( $video->embed_name ),
			)
		);
	}
}
