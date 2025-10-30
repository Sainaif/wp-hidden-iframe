<?php
/**
 * Videos Page Class
 *
 * Handles the main video manager admin page including CRUD operations,
 * search, sorting, and pagination.
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
 * Class Videos_Page
 *
 * Main CRUD interface for managing secure video embeddings.
 */
class Videos_Page {

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
	 * Main entry point for rendering the video manager page.
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

		// Get current page parameters
		$search_term  = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$default_sort = get_option( 'secure_embed_default_sort', 'DESC' );
		$order        = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : $default_sort;
		$edit_id      = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		// Get pagination setting
		$per_page = get_option( 'secure_embed_pagination_per_page', 20 );

		// Get videos and count
		$videos = $this->database->get_videos( $search_term, $order, $per_page, $current_page );
		$total_videos = $this->database->get_videos_count( $search_term );
		$total_pages = ceil( $total_videos / $per_page );

		// Get video being edited if applicable
		$edit_video = $edit_id ? $this->database->get_video( $edit_id ) : null;

		// Get settings
		$input_width = get_option( 'secure_embed_input_width', 400 );
		$show_edit_delete = get_option( 'secure_embed_show_edit_delete', 'on' );

		// Render the page HTML
		$this->render_page_html( $videos, $total_videos, $total_pages, $current_page, $search_term, $order, $edit_video, $input_width, $show_edit_delete );
	}

	/**
	 * Handle form submissions
	 *
	 * Processes add, edit, and delete requests.
	 *
	 * @since      1.0.0
	 */
	private function handle_form_submissions() {
		// Handle add video
		if ( isset( $_POST['add_video'] ) ) {
			check_admin_referer( 'secure_embed_add_video' );

			$db_name = isset( $_POST['db_name'] ) ? sanitize_text_field( wp_unslash( $_POST['db_name'] ) ) : '';
			$embed_name = isset( $_POST['embed_name'] ) ? sanitize_text_field( wp_unslash( $_POST['embed_name'] ) ) : '';
			$link = isset( $_POST['link'] ) ? esc_url_raw( wp_unslash( $_POST['link'] ) ) : '';

			if ( ! empty( $db_name ) && ! empty( $embed_name ) && ! empty( $link ) ) {
				$result = $this->database->create_video( $db_name, $embed_name, $link );
				if ( $result ) {
					$this->show_admin_notice( __( 'Video added successfully!', 'secure-hash-embed-manager' ), 'success' );
				} else {
					$this->show_admin_notice( __( 'Failed to add video. Please check the URL is valid.', 'secure-hash-embed-manager' ), 'error' );
				}
			} else {
				$this->show_admin_notice( __( 'Please fill out all fields.', 'secure-hash-embed-manager' ), 'error' );
			}
		}

		// Handle edit video
		if ( isset( $_POST['edit_video'] ) ) {
			check_admin_referer( 'secure_embed_edit_video' );

			$id = isset( $_POST['edit_id'] ) ? absint( $_POST['edit_id'] ) : 0;
			$db_name = isset( $_POST['edit_db_name'] ) ? sanitize_text_field( wp_unslash( $_POST['edit_db_name'] ) ) : '';
			$embed_name = isset( $_POST['edit_embed_name'] ) ? sanitize_text_field( wp_unslash( $_POST['edit_embed_name'] ) ) : '';
			$link = isset( $_POST['edit_link'] ) ? esc_url_raw( wp_unslash( $_POST['edit_link'] ) ) : '';

			if ( $id && ! empty( $db_name ) && ! empty( $embed_name ) && ! empty( $link ) ) {
				$result = $this->database->update_video( $id, $db_name, $embed_name, $link );
				if ( $result ) {
					$this->show_admin_notice( __( 'Video updated successfully!', 'secure-hash-embed-manager' ), 'success' );
					// Redirect to remove edit parameter
					wp_safe_redirect( admin_url( 'admin.php?page=secure-embed' ) );
					exit;
				} else {
					$this->show_admin_notice( __( 'Failed to update video. Please check the URL is valid.', 'secure-hash-embed-manager' ), 'error' );
				}
			} else {
				$this->show_admin_notice( __( 'Please fill out all fields.', 'secure-hash-embed-manager' ), 'error' );
			}
		}

		// Handle delete video
		if ( isset( $_POST['delete_video'] ) ) {
			check_admin_referer( 'secure_embed_delete_video' );

			$id = isset( $_POST['delete_id'] ) ? absint( $_POST['delete_id'] ) : 0;

			if ( $id ) {
				$result = $this->database->delete_video( $id );
				if ( $result ) {
					$this->show_admin_notice( __( 'Video deleted successfully!', 'secure-hash-embed-manager' ), 'success' );
				} else {
					$this->show_admin_notice( __( 'Failed to delete video.', 'secure-hash-embed-manager' ), 'error' );
				}
			}
		}
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
			'secure_embed_messages',
			'secure_embed_message',
			$message,
			$type
		);
		settings_errors( 'secure_embed_messages' );
	}

	/**
	 * Render page HTML
	 *
	 * Outputs the complete HTML for the video manager page.
	 *
	 * @since      1.0.0
	 * @param    array   $videos            Array of video objects.
	 * @param    int     $total_videos      Total video count.
	 * @param    int     $total_pages       Total pages for pagination.
	 * @param    int     $current_page      Current page number.
	 * @param    string  $search_term       Current search term.
	 * @param    string  $order             Current sort order.
	 * @param    object  $edit_video        Video being edited (if any).
	 * @param    int     $input_width       Input field width setting.
	 * @param    string  $show_edit_delete  Whether to show edit/delete buttons.
	 */
	private function render_page_html( $videos, $total_videos, $total_pages, $current_page, $search_term, $order, $edit_video, $input_width, $show_edit_delete ) {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Secure Embed Manager', 'secure-hash-embed-manager' ); ?></h1>
			<p><?php esc_html_e( 'Add and manage secure video links with hashed IDs to hide real URLs.', 'secure-hash-embed-manager' ); ?></p>

			<?php if ( $edit_video ) : ?>
				<!-- Edit Video Form -->
				<div class="secure-embed-card">
					<h2><?php esc_html_e( 'Edit Video', 'secure-hash-embed-manager' ); ?></h2>
					<form method="post" action="">
						<?php wp_nonce_field( 'secure_embed_edit_video' ); ?>
						<input type="hidden" name="edit_id" value="<?php echo esc_attr( $edit_video->id ); ?>">

						<table class="form-table">
							<tr>
								<th><label for="edit_db_name"><?php esc_html_e( 'Admin Name (Internal)', 'secure-hash-embed-manager' ); ?></label></th>
								<td><input type="text" id="edit_db_name" name="edit_db_name" value="<?php echo esc_attr( $edit_video->db_name ); ?>" style="width:<?php echo esc_attr( $input_width ); ?>px;" required></td>
							</tr>
							<tr>
								<th><label for="edit_embed_name"><?php esc_html_e( 'Display Name (Public)', 'secure-hash-embed-manager' ); ?></label></th>
								<td><input type="text" id="edit_embed_name" name="edit_embed_name" value="<?php echo esc_attr( $edit_video->embed_name ); ?>" style="width:<?php echo esc_attr( $input_width ); ?>px;" required></td>
							</tr>
							<tr>
								<th><label for="edit_link"><?php esc_html_e( 'Video URL', 'secure-hash-embed-manager' ); ?></label></th>
								<td><input type="url" id="edit_link" name="edit_link" value="<?php echo esc_attr( $edit_video->link ); ?>" style="width:<?php echo esc_attr( $input_width ); ?>px;" required></td>
							</tr>
						</table>

						<?php submit_button( __( 'Update Video', 'secure-hash-embed-manager' ), 'primary', 'edit_video' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=secure-embed' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'secure-hash-embed-manager' ); ?></a>
					</form>
				</div>
			<?php else : ?>
				<!-- Add New Video Form -->
				<div class="secure-embed-card">
					<h2><?php esc_html_e( 'Add New Video', 'secure-hash-embed-manager' ); ?></h2>
					<form method="post" action="">
						<?php wp_nonce_field( 'secure_embed_add_video' ); ?>

						<table class="form-table">
							<tr>
								<th><label for="db_name"><?php esc_html_e( 'Admin Name (Internal)', 'secure-hash-embed-manager' ); ?></label></th>
								<td><input type="text" id="db_name" name="db_name" style="width:<?php echo esc_attr( $input_width ); ?>px;" required></td>
							</tr>
							<tr>
								<th><label for="embed_name"><?php esc_html_e( 'Display Name (Public)', 'secure-hash-embed-manager' ); ?></label></th>
								<td><input type="text" id="embed_name" name="embed_name" style="width:<?php echo esc_attr( $input_width ); ?>px;" required></td>
							</tr>
							<tr>
								<th><label for="link"><?php esc_html_e( 'Video URL', 'secure-hash-embed-manager' ); ?></label></th>
								<td><input type="url" id="link" name="link" style="width:<?php echo esc_attr( $input_width ); ?>px;" required></td>
							</tr>
						</table>

						<?php submit_button( __( 'Add Video', 'secure-hash-embed-manager' ), 'primary', 'add_video' ); ?>
					</form>
				</div>
			<?php endif; ?>

			<!-- Search and Sort Controls -->
			<div class="secure-embed-card">
				<h2><?php esc_html_e( 'Video List', 'secure-hash-embed-manager' ); ?> (<?php echo esc_html( $total_videos ); ?>)</h2>

				<div class="tablenav top">
					<div class="alignleft actions">
						<form method="get" action="">
							<input type="hidden" name="page" value="secure-embed">
							<input type="text" name="search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php esc_attr_e( 'Search videos...', 'secure-hash-embed-manager' ); ?>" style="width:200px;">
							<button type="submit" class="button"><?php esc_html_e( 'Search', 'secure-hash-embed-manager' ); ?></button>
							<?php if ( $search_term ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=secure-embed' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'secure-hash-embed-manager' ); ?></a>
							<?php endif; ?>
						</form>
					</div>

					<div class="alignright actions">
						<?php
						$new_order = ( 'ASC' === $order ) ? 'DESC' : 'ASC';
						$order_url = add_query_arg( array(
							'page'   => 'secure-embed',
							'order'  => $new_order,
							'search' => $search_term,
						), admin_url( 'admin.php' ) );
						?>
						<a href="<?php echo esc_url( $order_url ); ?>" class="button">
							<?php
							echo 'ASC' === $order
								? esc_html__( 'Sort: Oldest First', 'secure-hash-embed-manager' )
								: esc_html__( 'Sort: Newest First', 'secure-hash-embed-manager' );
							?>
						</a>
					</div>
				</div>

				<?php if ( empty( $videos ) ) : ?>
					<p><?php esc_html_e( 'No videos found.', 'secure-hash-embed-manager' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width:5%;"><?php esc_html_e( 'ID', 'secure-hash-embed-manager' ); ?></th>
								<th style="width:15%;"><?php esc_html_e( 'Admin Name', 'secure-hash-embed-manager' ); ?></th>
								<th style="width:15%;"><?php esc_html_e( 'Display Name', 'secure-hash-embed-manager' ); ?></th>
								<th style="width:25%;"><?php esc_html_e( 'Video URL', 'secure-hash-embed-manager' ); ?></th>
								<th style="width:15%;"><?php esc_html_e( 'Unique ID', 'secure-hash-embed-manager' ); ?></th>
								<th style="width:25%;"><?php esc_html_e( 'Actions', 'secure-hash-embed-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $videos as $video ) : ?>
								<tr>
									<td><?php echo esc_html( $video->id ); ?></td>
									<td><?php echo esc_html( $video->db_name ); ?></td>
									<td><?php echo esc_html( $video->embed_name ); ?></td>
									<td><a href="<?php echo esc_url( $video->link ); ?>" target="_blank"><?php echo esc_html( wp_trim_words( $video->link, 8 ) ); ?></a></td>
									<td><code><?php echo esc_html( $video->unique_id ); ?></code></td>
									<td>
										<button type="button" class="button button-small secure-embed-copy-btn" data-id="<?php echo esc_attr( $video->unique_id ); ?>" data-name="<?php echo esc_attr( $video->embed_name ); ?>">
											<?php esc_html_e( 'Copy Embed', 'secure-hash-embed-manager' ); ?>
										</button>

										<?php if ( 'on' === $show_edit_delete ) : ?>
											<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'secure-embed', 'edit' => $video->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
												<?php esc_html_e( 'Edit', 'secure-hash-embed-manager' ); ?>
											</a>

											<form method="post" action="" style="display:inline;" class="secure-embed-delete-form">
												<?php wp_nonce_field( 'secure_embed_delete_video' ); ?>
												<input type="hidden" name="delete_id" value="<?php echo esc_attr( $video->id ); ?>">
												<button type="submit" name="delete_video" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this video?', 'secure-hash-embed-manager' ) ); ?>');">
													<?php esc_html_e( 'Delete', 'secure-hash-embed-manager' ); ?>
												</button>
											</form>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<!-- Pagination -->
					<?php if ( $total_pages > 1 ) : ?>
						<div class="tablenav bottom">
							<div class="tablenav-pages">
								<span class="displaying-num">
									<?php
									/* translators: %s: number of items */
									printf( esc_html( _n( '%s item', '%s items', $total_videos, 'secure-hash-embed-manager' ) ), number_format_i18n( $total_videos ) );
									?>
								</span>
								<?php
								$page_links = paginate_links( array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => __( '&laquo;', 'secure-hash-embed-manager' ),
									'next_text' => __( '&raquo;', 'secure-hash-embed-manager' ),
									'total'     => $total_pages,
									'current'   => $current_page,
								) );

								if ( $page_links ) {
									echo '<span class="pagination-links">' . $page_links . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								?>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<!-- Usage Instructions -->
			<div class="secure-embed-card">
				<h2><?php esc_html_e( 'How to Use', 'secure-hash-embed-manager' ); ?></h2>
				<p><?php esc_html_e( 'To embed a video on your site, use this HTML structure:', 'secure-hash-embed-manager' ); ?></p>
				<pre><code>&lt;div class="video-container"&gt;
    &lt;a href="#" class="video-toggler" data-id="vid_XXXXXXXXXX"&gt;Video Name&lt;/a&gt;
    &lt;div class="video-content" style="display:none;"&gt;
        &lt;iframe src="about:blank" frameborder="0" width="640" height="360" allowfullscreen&gt;&lt;/iframe&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
				<p><?php esc_html_e( 'Replace "vid_XXXXXXXXXX" with the unique ID from the table above, and "Video Name" with your desired link text.', 'secure-hash-embed-manager' ); ?></p>
			</div>
		</div>

		<!-- Hidden textarea for copying embed code -->
		<textarea id="secure-embed-copy-textarea" style="position:absolute;left:-9999px;"></textarea>
		<?php
	}
}
