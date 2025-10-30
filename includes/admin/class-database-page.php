<?php
/**
 * Database Management Page Class
 *
 * Handles database backup, restore, import, and reset operations.
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
 * Class Database_Page
 *
 * Manages database import/export and maintenance operations.
 */
class Database_Page {

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
	 * Render the page
	 *
	 * Main entry point for rendering the database management page.
	 *
	 * @since      1.0.0
	 */
	public function render() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'secure-hash-embed-manager' ) );
		}

		// Handle form submissions
		$this->handle_form_submissions();

		// Render the page HTML
		$this->render_page_html();
	}

	/**
	 * Handle form submissions
	 *
	 * Processes backup download, restore, import, and reset requests.
	 *
	 * @since      1.0.0
	 */
	private function handle_form_submissions() {
		// Handle JSON backup download
		if ( isset( $_POST['download_backup'] ) ) {
			check_admin_referer( 'secure_embed_download_backup' );
			$this->download_json_backup();
			exit;
		}

		// Handle JSON restore
		if ( isset( $_POST['restore_backup'] ) ) {
			check_admin_referer( 'secure_embed_restore_backup' );

			// Verify confirmation text
			$confirmation = isset( $_POST['restore_confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['restore_confirm'] ) ) : '';
			if ( 'I know this is dangerous and I want to do it' !== $confirmation ) {
				$this->show_admin_notice( __( 'Incorrect confirmation text. Restore cancelled.', 'secure-hash-embed-manager' ), 'error' );
				return;
			}

			// Check if file was uploaded
			if ( ! isset( $_FILES['restore_file'] ) || UPLOAD_ERR_OK !== $_FILES['restore_file']['error'] ) {
				$this->show_admin_notice( __( 'Please select a JSON file to restore.', 'secure-hash-embed-manager' ), 'error' );
				return;
			}

			// Validate file type
			$file_type = wp_check_filetype( $_FILES['restore_file']['name'] );
			if ( 'json' !== $file_type['ext'] ) {
				$this->show_admin_notice( __( 'Please upload a valid JSON file.', 'secure-hash-embed-manager' ), 'error' );
				return;
			}

			// Read file content
			$json_data = file_get_contents( $_FILES['restore_file']['tmp_name'] );
			$result = $this->database->import_from_json( $json_data );

			if ( $result['success'] ) {
				$this->show_admin_notice( $result['message'], 'success' );
			} else {
				$this->show_admin_notice( $result['message'], 'error' );
			}
		}

		// Handle CSV import
		if ( isset( $_POST['import_csv'] ) ) {
			check_admin_referer( 'secure_embed_import_csv' );

			// Check if file was uploaded
			if ( ! isset( $_FILES['csv_file'] ) || UPLOAD_ERR_OK !== $_FILES['csv_file']['error'] ) {
				$this->show_admin_notice( __( 'Please select a CSV file to import.', 'secure-hash-embed-manager' ), 'error' );
				return;
			}

			// Validate file type
			$file_type = wp_check_filetype( $_FILES['csv_file']['name'] );
			if ( ! in_array( $file_type['ext'], array( 'csv', 'txt' ), true ) ) {
				$this->show_admin_notice( __( 'Please upload a valid CSV file.', 'secure-hash-embed-manager' ), 'error' );
				return;
			}

			// Read file content
			$csv_data = file_get_contents( $_FILES['csv_file']['tmp_name'] );
			$result = $this->database->import_from_csv( $csv_data );

			if ( $result['success'] ) {
				$this->show_admin_notice( $result['message'], 'success' );
			} else {
				$this->show_admin_notice( $result['message'], 'error' );
			}
		}

		// Handle database reset
		if ( isset( $_POST['reset_database'] ) ) {
			check_admin_referer( 'secure_embed_reset_database' );

			// Verify confirmation text
			$confirmation = isset( $_POST['reset_confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['reset_confirm'] ) ) : '';
			if ( 'I know this is dangerous and I want to do it' !== $confirmation ) {
				$this->show_admin_notice( __( 'Incorrect confirmation text. Reset cancelled.', 'secure-hash-embed-manager' ), 'error' );
				return;
			}

			$result = $this->database->reset_table();
			if ( $result ) {
				$this->show_admin_notice( __( 'Database reset successfully. All videos have been deleted.', 'secure-hash-embed-manager' ), 'success' );
			} else {
				$this->show_admin_notice( __( 'Failed to reset database.', 'secure-hash-embed-manager' ), 'error' );
			}
		}
	}

	/**
	 * Download JSON backup
	 *
	 * Generates and sends a JSON backup file for download.
	 *
	 * @since      1.0.0
	 */
	private function download_json_backup() {
		$json_data = $this->database->export_to_json();
		$filename = 'secure-embed-backup-' . gmdate( 'Y-m-d-His' ) . '.json';

		// Set headers for file download
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json_data ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Show admin notice
	 *
	 * Displays a WordPress admin notice.
	 *
	 * @since      1.0.0
	 * @param    string  $message    Notice message.
	 * @param    string  $type       Notice type (success, error, warning, info).
	 */
	private function show_admin_notice( $message, $type = 'info' ) {
		add_settings_error(
			'secure_embed_db_messages',
			'secure_embed_db_message',
			$message,
			$type
		);
		settings_errors( 'secure_embed_db_messages' );
	}

	/**
	 * Render page HTML
	 *
	 * Outputs the complete HTML for the database management page.
	 *
	 * @since      1.0.0
	 */
	private function render_page_html() {
		$total_videos = $this->database->get_videos_count();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Database Management', 'secure-hash-embed-manager' ); ?></h1>
			<p>
				<?php
				/* translators: %d: number of videos in database */
				printf( esc_html__( 'Current database contains %d videos.', 'secure-hash-embed-manager' ), esc_html( $total_videos ) );
				?>
			</p>

			<!-- JSON Backup Download -->
			<div class="secure-embed-card">
				<h2><?php esc_html_e( 'Download Backup (JSON)', 'secure-hash-embed-manager' ); ?></h2>
				<p><?php esc_html_e( 'Download all videos as a JSON backup file. This file can be used to restore your data later.', 'secure-hash-embed-manager' ); ?></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'secure_embed_download_backup' ); ?>
					<?php submit_button( __( 'Download Database as JSON', 'secure-hash-embed-manager' ), 'secondary', 'download_backup', false ); ?>
				</form>
			</div>

			<!-- JSON Restore -->
			<div class="secure-embed-card">
				<h2><?php esc_html_e( 'Restore from Backup (JSON)', 'secure-hash-embed-manager' ); ?></h2>
				<p class="description" style="color: #d63638;">
					<strong><?php esc_html_e( 'WARNING:', 'secure-hash-embed-manager' ); ?></strong>
					<?php esc_html_e( 'This will DELETE all current videos and replace them with the backup data. This action cannot be undone!', 'secure-hash-embed-manager' ); ?>
				</p>
				<form method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'secure_embed_restore_backup' ); ?>

					<table class="form-table">
						<tr>
							<th><label for="restore_file"><?php esc_html_e( 'Select JSON File', 'secure-hash-embed-manager' ); ?></label></th>
							<td><input type="file" id="restore_file" name="restore_file" accept=".json" required></td>
						</tr>
						<tr>
							<th><label for="restore_confirm"><?php esc_html_e( 'Confirmation', 'secure-hash-embed-manager' ); ?></label></th>
							<td>
								<input type="text" id="restore_confirm" name="restore_confirm" style="width:400px;" required>
								<p class="description">
									<?php esc_html_e( 'Type exactly:', 'secure-hash-embed-manager' ); ?>
									<code>I know this is dangerous and I want to do it</code>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Restore from Backup', 'secure-hash-embed-manager' ), 'delete', 'restore_backup' ); ?>
				</form>
			</div>

			<!-- CSV Import -->
			<div class="secure-embed-card">
				<h2><?php esc_html_e( 'Import from CSV', 'secure-hash-embed-manager' ); ?></h2>
				<p><?php esc_html_e( 'Import videos from a CSV file. The CSV should have columns: db_name, embed_name, link', 'secure-hash-embed-manager' ); ?></p>
				<p class="description"><?php esc_html_e( 'Duplicate links will be skipped. New unique IDs will be generated automatically.', 'secure-hash-embed-manager' ); ?></p>

				<h3><?php esc_html_e( 'CSV Format Example:', 'secure-hash-embed-manager' ); ?></h3>
				<pre><code>db_name,embed_name,link
Admin Video 1,Public Name 1,https://example.com/video1
Admin Video 2,Public Name 2,https://example.com/video2</code></pre>

				<form method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'secure_embed_import_csv' ); ?>

					<table class="form-table">
						<tr>
							<th><label for="csv_file"><?php esc_html_e( 'Select CSV File', 'secure-hash-embed-manager' ); ?></label></th>
							<td><input type="file" id="csv_file" name="csv_file" accept=".csv,.txt" required></td>
						</tr>
					</table>

					<?php submit_button( __( 'Import from CSV', 'secure-hash-embed-manager' ), 'secondary', 'import_csv' ); ?>
				</form>
			</div>

			<!-- Database Reset -->
			<div class="secure-embed-card">
				<h2><?php esc_html_e( 'Reset Database', 'secure-hash-embed-manager' ); ?></h2>
				<p class="description" style="color: #d63638;">
					<strong><?php esc_html_e( 'DANGER:', 'secure-hash-embed-manager' ); ?></strong>
					<?php esc_html_e( 'This will DROP the database table and recreate it empty. ALL videos will be permanently deleted. This action cannot be undone!', 'secure-hash-embed-manager' ); ?>
				</p>
				<form method="post" action="">
					<?php wp_nonce_field( 'secure_embed_reset_database' ); ?>

					<table class="form-table">
						<tr>
							<th><label for="reset_confirm"><?php esc_html_e( 'Confirmation', 'secure-hash-embed-manager' ); ?></label></th>
							<td>
								<input type="text" id="reset_confirm" name="reset_confirm" style="width:400px;" required>
								<p class="description">
									<?php esc_html_e( 'Type exactly:', 'secure-hash-embed-manager' ); ?>
									<code>I know this is dangerous and I want to do it</code>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Reset Database', 'secure-hash-embed-manager' ), 'delete', 'reset_database' ); ?>
				</form>
			</div>

			<!-- Tips -->
			<div class="secure-embed-card">
				<h2><?php esc_html_e( 'Tips', 'secure-hash-embed-manager' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Always create a backup before performing restore or reset operations.', 'secure-hash-embed-manager' ); ?></li>
					<li><?php esc_html_e( 'JSON backups preserve unique IDs, while CSV imports generate new ones.', 'secure-hash-embed-manager' ); ?></li>
					<li><?php esc_html_e( 'CSV import skips duplicate URLs automatically to prevent data duplication.', 'secure-hash-embed-manager' ); ?></li>
					<li><?php esc_html_e( 'Database reset is useful for starting fresh or troubleshooting issues.', 'secure-hash-embed-manager' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}
}
