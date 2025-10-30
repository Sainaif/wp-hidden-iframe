<?php
/**
 * Settings Page Class
 *
 * Handles plugin configuration and settings management.
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
 * Class Settings_Page
 *
 * Manages plugin settings and configuration options.
 */
class Settings_Page {

	/**
	 * Constructor
	 *
	 * @since      1.0.0
	 */
	public function __construct() {
		// Constructor intentionally left minimal
	}

	/**
	 * Render the page
	 *
	 * Main entry point for rendering the settings page.
	 *
	 * @since      1.0.0
	 */
	public function render() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'secure-hash-embed-manager' ) );
		}

		// Handle form submission
		$this->handle_form_submission();

		// Get current settings
		$devtools_enabled = get_option( 'secure_embed_block_devtools', 'off' );
		$devtools_url = get_option( 'secure_embed_block_devtools_url', site_url( '/404' ) );
		$input_width = get_option( 'secure_embed_input_width', 400 );
		$show_edit_delete = get_option( 'secure_embed_show_edit_delete', 'on' );
		$per_page = get_option( 'secure_embed_pagination_per_page', 20 );
		$default_sort = get_option( 'secure_embed_default_sort', 'ASC' );

		// Render the page HTML
		$this->render_page_html( $devtools_enabled, $devtools_url, $input_width, $show_edit_delete, $per_page, $default_sort );
	}

	/**
	 * Handle form submission
	 *
	 * Processes settings updates.
	 *
	 * @since      1.0.0
	 */
	private function handle_form_submission() {
		if ( ! isset( $_POST['save_settings'] ) ) {
			return;
		}

		check_admin_referer( 'secure_embed_save_settings' );

		// DevTools blocking toggle
		$devtools_enabled = isset( $_POST['block_devtools'] ) ? sanitize_text_field( wp_unslash( $_POST['block_devtools'] ) ) : 'off';
		if ( ! in_array( $devtools_enabled, array( 'on', 'off' ), true ) ) {
			$devtools_enabled = 'off';
		}
		update_option( 'secure_embed_block_devtools', $devtools_enabled );

		// DevTools redirect URL
		$devtools_url = isset( $_POST['block_devtools_url'] ) ? esc_url_raw( wp_unslash( $_POST['block_devtools_url'] ) ) : site_url( '/404' );
		update_option( 'secure_embed_block_devtools_url', $devtools_url );

		// Admin input width
		$input_width = isset( $_POST['input_width'] ) ? absint( $_POST['input_width'] ) : 400;
		if ( $input_width < 50 || $input_width > 2000 ) {
			$input_width = 400;
		}
		update_option( 'secure_embed_input_width', $input_width );

		// Show/hide edit/delete buttons
		$show_edit_delete = isset( $_POST['show_edit_delete'] ) ? sanitize_text_field( wp_unslash( $_POST['show_edit_delete'] ) ) : 'on';
		if ( ! in_array( $show_edit_delete, array( 'on', 'off' ), true ) ) {
			$show_edit_delete = 'on';
		}
		update_option( 'secure_embed_show_edit_delete', $show_edit_delete );

		// Pagination per page
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
		if ( ! in_array( $per_page, array( 10, 20, 50, 100 ), true ) ) {
			$per_page = 20;
		}
		update_option( 'secure_embed_pagination_per_page', $per_page );

		// Default sort order
		$default_sort = isset( $_POST['default_sort'] ) ? sanitize_text_field( wp_unslash( $_POST['default_sort'] ) ) : 'ASC';
		if ( ! in_array( $default_sort, array( 'ASC', 'DESC' ), true ) ) {
			$default_sort = 'ASC';
		}
		update_option( 'secure_embed_default_sort', $default_sort );

		// Show success message
		$this->show_admin_notice( __( 'Settings saved successfully!', 'secure-hash-embed-manager' ), 'success' );
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
			'secure_embed_settings_messages',
			'secure_embed_settings_message',
			$message,
			$type
		);
		settings_errors( 'secure_embed_settings_messages' );
	}

	/**
	 * Render page HTML
	 *
	 * Outputs the complete HTML for the settings page.
	 *
	 * @since      1.0.0
	 * @param    string  $devtools_enabled    Whether DevTools blocking is enabled.
	 * @param    string  $devtools_url        DevTools redirect URL.
	 * @param    int     $input_width         Admin input field width.
	 * @param    string  $show_edit_delete    Whether to show edit/delete buttons.
	 * @param    int     $per_page            Records per page.
	 * @param    string  $default_sort        Default sort order.
	 */
	private function render_page_html( $devtools_enabled, $devtools_url, $input_width, $show_edit_delete, $per_page, $default_sort ) {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Plugin Settings', 'secure-hash-embed-manager' ); ?></h1>
			<p><?php esc_html_e( 'Configure plugin behavior and appearance.', 'secure-hash-embed-manager' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'secure_embed_save_settings' ); ?>

				<!-- Security Settings -->
				<div class="secure-embed-card">
					<h2><?php esc_html_e( 'Security Settings', 'secure-hash-embed-manager' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Block DevTools', 'secure-hash-embed-manager' ); ?></th>
							<td>
								<label>
									<input type="radio" name="block_devtools" value="on" <?php checked( $devtools_enabled, 'on' ); ?>>
									<?php esc_html_e( 'Enabled', 'secure-hash-embed-manager' ); ?>
								</label>
								<br>
								<label>
									<input type="radio" name="block_devtools" value="off" <?php checked( $devtools_enabled, 'off' ); ?>>
									<?php esc_html_e( 'Disabled', 'secure-hash-embed-manager' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Enable browser DevTools detection and blocking on the frontend. When enabled, users who open DevTools will be redirected.', 'secure-hash-embed-manager' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="block_devtools_url"><?php esc_html_e( 'Redirect URL', 'secure-hash-embed-manager' ); ?></label></th>
							<td>
								<input type="url" id="block_devtools_url" name="block_devtools_url" value="<?php echo esc_attr( $devtools_url ); ?>" style="width:400px;">
								<p class="description">
									<?php esc_html_e( 'URL to redirect users when DevTools are detected. Default is your site\'s 404 page.', 'secure-hash-embed-manager' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Interface Settings -->
				<div class="secure-embed-card">
					<h2><?php esc_html_e( 'Interface Settings', 'secure-hash-embed-manager' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row"><label for="input_width"><?php esc_html_e( 'Input Field Width', 'secure-hash-embed-manager' ); ?></label></th>
							<td>
								<input type="number" id="input_width" name="input_width" value="<?php echo esc_attr( $input_width ); ?>" min="50" max="2000" step="10">
								<?php esc_html_e( 'pixels', 'secure-hash-embed-manager' ); ?>
								<p class="description">
									<?php esc_html_e( 'Width of input fields in the admin interface (50-2000 pixels).', 'secure-hash-embed-manager' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Show Edit/Delete Buttons', 'secure-hash-embed-manager' ); ?></th>
							<td>
								<label>
									<input type="radio" name="show_edit_delete" value="on" <?php checked( $show_edit_delete, 'on' ); ?>>
									<?php esc_html_e( 'Show', 'secure-hash-embed-manager' ); ?>
								</label>
								<br>
								<label>
									<input type="radio" name="show_edit_delete" value="off" <?php checked( $show_edit_delete, 'off' ); ?>>
									<?php esc_html_e( 'Hide', 'secure-hash-embed-manager' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Show or hide edit and delete buttons in the video list table.', 'secure-hash-embed-manager' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="per_page"><?php esc_html_e( 'Videos Per Page', 'secure-hash-embed-manager' ); ?></label></th>
							<td>
								<select id="per_page" name="per_page">
									<option value="10" <?php selected( $per_page, 10 ); ?>>10</option>
									<option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
									<option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
									<option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Number of videos to display per page in the video list.', 'secure-hash-embed-manager' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Default Sort Order', 'secure-hash-embed-manager' ); ?></th>
							<td>
								<label>
									<input type="radio" name="default_sort" value="ASC" <?php checked( $default_sort, 'ASC' ); ?>>
									<?php esc_html_e( 'Oldest First (Ascending)', 'secure-hash-embed-manager' ); ?>
								</label>
								<br>
								<label>
									<input type="radio" name="default_sort" value="DESC" <?php checked( $default_sort, 'DESC' ); ?>>
									<?php esc_html_e( 'Newest First (Descending)', 'secure-hash-embed-manager' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Default sorting order for the video list.', 'secure-hash-embed-manager' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Save Settings', 'secure-hash-embed-manager' ), 'primary', 'save_settings' ); ?>
			</form>

			<!-- Plugin Information -->
			<div class="secure-embed-card">
				<h2><?php esc_html_e( 'Plugin Information', 'secure-hash-embed-manager' ); ?></h2>
				<table class="widefat">
					<tr>
						<th><?php esc_html_e( 'Version', 'secure-hash-embed-manager' ); ?></th>
						<td><?php echo esc_html( SECURE_EMBED_VERSION ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Author', 'secure-hash-embed-manager' ); ?></th>
						<td><a href="https://github.com/Sainaif" target="_blank">Sainaif</a></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'GitHub', 'secure-hash-embed-manager' ); ?></th>
						<td><a href="https://github.com/Sainaif/wp-hidden-iframe" target="_blank">https://github.com/Sainaif/wp-hidden-iframe</a></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'License', 'secure-hash-embed-manager' ); ?></th>
						<td>MIT</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Language', 'secure-hash-embed-manager' ); ?></th>
						<td>
							<?php
							$locale = get_locale();
							if ( 'pl_PL' === $locale ) {
								esc_html_e( 'Polish', 'secure-hash-embed-manager' );
							} else {
								esc_html_e( 'English', 'secure-hash-embed-manager' );
							}
							echo ' (' . esc_html( $locale ) . ')';
							?>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}
}
