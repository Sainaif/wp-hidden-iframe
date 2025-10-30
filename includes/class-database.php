<?php
/**
 * Database Operations Class
 *
 * Handles all database interactions including CRUD operations,
 * search, pagination, unique ID generation, and data import/export.
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
 * Class Database
 *
 * Manages all database operations for the secure embed videos.
 */
class Database {

	/**
	 * Database table name (without prefix)
	 *
	 * @var string
	 */
	private $table_name = 'secure_embed_videos';

	/**
	 * Constructor
	 *
	 * @since      1.0.0
	 */
	public function __construct() {
		// Constructor intentionally left minimal
	}

	/**
	 * Get the full table name with WordPress prefix
	 *
	 * @since      1.0.0
	 * @return   string    Full table name with prefix.
	 */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . $this->table_name;
	}

	/**
	 * Create a new video entry
	 *
	 * Adds a new video with a unique hashed ID to the database.
	 *
	 * @since      1.0.0
	 * @param    string  $db_name       Internal admin name.
	 * @param    string  $embed_name    Public display name.
	 * @param    string  $link          Actual video URL.
	 * @return   int|false              Insert ID on success, false on failure.
	 */
	public function create_video( $db_name, $embed_name, $link ) {
		global $wpdb;

		// Generate unique ID
		$unique_id = $this->generate_unique_id();

		// Validate URL format
		if ( ! filter_var( $link, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Insert the record
		$result = $wpdb->insert(
			$this->get_table_name(),
			array(
				'db_name'    => sanitize_text_field( $db_name ),
				'embed_name' => sanitize_text_field( $embed_name ),
				'link'       => esc_url_raw( $link ),
				'unique_id'  => $unique_id,
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing video entry
	 *
	 * Updates video information by ID.
	 *
	 * @since      1.0.0
	 * @param    int     $id            Video ID to update.
	 * @param    string  $db_name       Internal admin name.
	 * @param    string  $embed_name    Public display name.
	 * @param    string  $link          Actual video URL.
	 * @return   bool                   True on success, false on failure.
	 */
	public function update_video( $id, $db_name, $embed_name, $link ) {
		global $wpdb;

		// Validate URL format
		if ( ! filter_var( $link, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'db_name'    => sanitize_text_field( $db_name ),
				'embed_name' => sanitize_text_field( $embed_name ),
				'link'       => esc_url_raw( $link ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a video entry
	 *
	 * Deletes a video by ID and reindexes remaining IDs to eliminate gaps.
	 *
	 * @since      1.0.0
	 * @param    int  $id    Video ID to delete.
	 * @return   bool        True on success, false on failure.
	 */
	public function delete_video( $id ) {
		global $wpdb;

		// Delete the record
		$result = $wpdb->delete(
			$this->get_table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		if ( $result ) {
			// Reindex IDs to eliminate gaps (user requirement)
			$this->reindex_ids();
			return true;
		}

		return false;
	}

	/**
	 * Reindex table IDs to eliminate gaps
	 *
	 * After deletion, this maintains sequential IDs without gaps.
	 * Uses optimized single-query approach for better performance.
	 *
	 * @since      1.0.0
	 */
	private function reindex_ids() {
		global $wpdb;
		$table_name = $this->get_table_name();

		// Use MySQL variables for efficient reindexing in a single query
		// This is much faster than row-by-row updates
		$wpdb->query( 'SET @new_id = 0' );
		$wpdb->query(
			"UPDATE $table_name SET id = (@new_id := @new_id + 1) ORDER BY id ASC"
		);

		// Get the new maximum ID
		$max_id = $wpdb->get_var( "SELECT MAX(id) FROM $table_name" );
		$max_id = $max_id ? intval( $max_id ) : 0;

		// Reset AUTO_INCREMENT to max_id + 1
		$wpdb->query(
			$wpdb->prepare( "ALTER TABLE $table_name AUTO_INCREMENT = %d", $max_id + 1 )
		);
	}

	/**
	 * Get a single video by ID
	 *
	 * @since      1.0.0
	 * @param    int         $id    Video ID.
	 * @return   object|null        Video object or null if not found.
	 */
	public function get_video( $id ) {
		global $wpdb;

		$video = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE id = %d",
				absint( $id )
			)
		);

		return $video;
	}

	/**
	 * Get a video by unique ID
	 *
	 * Used by AJAX handler to retrieve the actual URL from hashed ID.
	 *
	 * @since      1.0.0
	 * @param    string      $unique_id    Unique hashed ID (vid_XXXXXXXXXX).
	 * @return   object|null               Video object or null if not found.
	 */
	public function get_video_by_unique_id( $unique_id ) {
		global $wpdb;

		$video = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE unique_id = %s",
				sanitize_text_field( $unique_id )
			)
		);

		return $video;
	}

	/**
	 * Get all videos with optional search and pagination
	 *
	 * @since      1.0.0
	 * @param    string  $search_term    Optional search term.
	 * @param    string  $order          Sort order (ASC or DESC).
	 * @param    int     $per_page       Number of records per page.
	 * @param    int     $page           Current page number.
	 * @return   array                   Array of video objects.
	 */
	public function get_videos( $search_term = '', $order = 'ASC', $per_page = 20, $page = 1 ) {
		global $wpdb;

		// Validate order direction (prevent SQL injection)
		$order = ( 'DESC' === strtoupper( $order ) ) ? 'DESC' : 'ASC';

		// Calculate offset for pagination
		$offset = ( max( 1, $page ) - 1 ) * $per_page;

		// Build the query
		$sql = "SELECT * FROM {$this->get_table_name()}";

		// Add search condition if search term provided
		if ( ! empty( $search_term ) ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $search_term ) ) . '%';
			$sql .= $wpdb->prepare(
				' WHERE db_name LIKE %s OR embed_name LIKE %s OR link LIKE %s OR unique_id LIKE %s',
				$like,
				$like,
				$like,
				$like
			);
		}

		// Add ordering and pagination
		$sql .= " ORDER BY id $order LIMIT %d OFFSET %d";

		// Execute query
		$videos = $wpdb->get_results(
			$wpdb->prepare( $sql, $per_page, $offset )
		);

		return $videos ? $videos : array();
	}

	/**
	 * Get total count of videos
	 *
	 * Used for pagination calculations.
	 *
	 * @since      1.0.0
	 * @param    string  $search_term    Optional search term.
	 * @return   int                     Total number of videos.
	 */
	public function get_videos_count( $search_term = '' ) {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$this->get_table_name()}";

		// Add search condition if search term provided
		if ( ! empty( $search_term ) ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $search_term ) ) . '%';
			$sql .= $wpdb->prepare(
				' WHERE db_name LIKE %s OR embed_name LIKE %s OR link LIKE %s OR unique_id LIKE %s',
				$like,
				$like,
				$like,
				$like
			);
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Generate a unique ID for new video entries
	 *
	 * Creates a unique identifier in format: vid_XXXXXXXXXX
	 *
	 * @since      1.0.0
	 * @return   string    Unique video ID.
	 */
	private function generate_unique_id() {
		global $wpdb;

		// Keep generating until we find a unique one
		$max_attempts = 100;
		$attempts     = 0;

		do {
			// Generate random 10-character alphanumeric string
			$random_str = substr( wp_generate_password( 10, false ), 0, 10 );
			$unique_id  = 'vid_' . $random_str;

			// Check if this ID already exists
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->get_table_name()} WHERE unique_id = %s",
					$unique_id
				)
			);

			$attempts++;

		} while ( $exists > 0 && $attempts < $max_attempts );

		// If we couldn't generate a unique ID after max attempts, add timestamp
		if ( $exists > 0 ) {
			$unique_id .= '_' . time();
		}

		return $unique_id;
	}

	/**
	 * Export all videos to JSON format
	 *
	 * Creates a JSON backup of all video entries with metadata.
	 *
	 * @since      1.0.0
	 * @return   string    JSON-encoded string of all videos.
	 */
	public function export_to_json() {
		global $wpdb;

		$videos = $wpdb->get_results(
			"SELECT * FROM {$this->get_table_name()} ORDER BY id ASC"
		);

		$export_data = array(
			'version'       => SECURE_EMBED_VERSION,
			'exported_at'   => current_time( 'mysql' ),
			'site_url'      => site_url(),
			'total_records' => count( $videos ),
			'videos'        => $videos,
		);

		return wp_json_encode( $export_data, JSON_PRETTY_PRINT );
	}

	/**
	 * Import videos from JSON data
	 *
	 * Imports videos from a JSON backup, replacing existing data.
	 *
	 * @since      1.0.0
	 * @param    string  $json_data    JSON string containing video data.
	 * @return   array                 Array with success status and message.
	 */
	public function import_from_json( $json_data ) {
		global $wpdb;

		// Decode JSON
		$data = json_decode( $json_data, true );

		if ( null === $data || ! isset( $data['videos'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid JSON data', 'secure-hash-embed-manager' ),
			);
		}

		// Clear existing data
		$wpdb->query( "TRUNCATE TABLE {$this->get_table_name()}" );

		// Insert videos
		$imported = 0;
		foreach ( $data['videos'] as $video ) {
			$result = $wpdb->insert(
				$this->get_table_name(),
				array(
					'db_name'    => sanitize_text_field( $video['db_name'] ),
					'embed_name' => sanitize_text_field( $video['embed_name'] ),
					'link'       => esc_url_raw( $video['link'] ),
					'unique_id'  => sanitize_text_field( $video['unique_id'] ),
				),
				array( '%s', '%s', '%s', '%s' )
			);

			if ( $result ) {
				$imported++;
			}
		}

		// Reindex to ensure sequential IDs
		$this->reindex_ids();

		return array(
			'success' => true,
			/* translators: %d: number of imported videos */
			'message' => sprintf( __( 'Successfully imported %d videos', 'secure-hash-embed-manager' ), $imported ),
			'count'   => $imported,
		);
	}

	/**
	 * Import videos from CSV data
	 *
	 * Imports videos from CSV format. Expected columns: db_name, embed_name, link
	 *
	 * @since      1.0.0
	 * @param    string  $csv_data    CSV string containing video data.
	 * @return   array                Array with success status and message.
	 */
	public function import_from_csv( $csv_data ) {
		// Parse CSV
		$lines = explode( "\n", $csv_data );

		if ( empty( $lines ) ) {
			return array(
				'success' => false,
				'message' => __( 'CSV file is empty', 'secure-hash-embed-manager' ),
			);
		}

		// Skip header row if it exists
		$first_line = strtolower( trim( $lines[0] ) );
		if ( strpos( $first_line, 'db_name' ) !== false || strpos( $first_line, 'embed_name' ) !== false ) {
			array_shift( $lines );
		}

		$imported = 0;
		$skipped  = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			// Parse CSV line
			$fields = str_getcsv( $line );

			if ( count( $fields ) < 3 ) {
				$skipped++;
				continue;
			}

			$db_name    = $fields[0];
			$embed_name = $fields[1];
			$link       = $fields[2];

			// Check for duplicates
			if ( $this->link_exists( $link ) ) {
				$skipped++;
				continue;
			}

			// Create video
			$result = $this->create_video( $db_name, $embed_name, $link );

			if ( $result ) {
				$imported++;
			} else {
				$skipped++;
			}
		}

		return array(
			'success' => true,
			/* translators: 1: number of imported videos, 2: number of skipped videos */
			'message' => sprintf( __( 'Imported %1$d videos, skipped %2$d duplicates/errors', 'secure-hash-embed-manager' ), $imported, $skipped ),
			'imported' => $imported,
			'skipped' => $skipped,
		);
	}

	/**
	 * Check if a link already exists in the database
	 *
	 * Used for duplicate detection during CSV import.
	 *
	 * @since      1.0.0
	 * @param    string  $link    URL to check.
	 * @return   bool             True if link exists, false otherwise.
	 */
	private function link_exists( $link ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->get_table_name()} WHERE link = %s",
				esc_url_raw( $link )
			)
		);

		return $count > 0;
	}

	/**
	 * Reset the database table
	 *
	 * Drops and recreates the table, removing all data.
	 * Use with caution - this is destructive!
	 *
	 * @since      1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function reset_table() {
		global $wpdb;

		// Drop the table
		$wpdb->query( "DROP TABLE IF EXISTS {$this->get_table_name()}" );

		// Recreate it
		require_once SECURE_EMBED_PLUGIN_DIR . 'includes/class-activator.php';
		Activator::activate();

		return true;
	}
}
