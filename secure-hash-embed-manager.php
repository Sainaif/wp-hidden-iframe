<?php
/*
Plugin Name: Secure Hash & Embed Manager
Description: Manages secure links with hashed IDs, hides real URLs, includes DevTools blocking plus sorting/searching.
Version: 0.9.4
Author: Sainaif | https://github.com/Sainaif
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin version consistently at the top
if ( ! defined( 'SECURE_EMBED_VERSION' ) ) {
    define( 'SECURE_EMBED_VERSION', '0.9.4' );
}

/* ============================================================
    1) CREATE DATABASE TABLE ON ACTIVATION
    ============================================================ */
/**
 * Creates the custom database table on plugin activation and sets default options.
 *
 * This function creates a table for storing video entries if it does not already exist.
 * It also initializes several plugin options with default values.
 */
register_activation_hook( __FILE__, 'secure_embed_create_table' );
function secure_embed_create_table() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'secure_embed_videos';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        db_name VARCHAR(255) NOT NULL,
        embed_name VARCHAR(255) NOT NULL,
        link TEXT NOT NULL,
        unique_id VARCHAR(100) NOT NULL UNIQUE,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Set default options (not output to browser)
    if ( get_option( 'secure_embed_block_devtools' ) === false ) {
        update_option( 'secure_embed_block_devtools', 'off' );
    }
    if ( get_option( 'secure_embed_block_devtools_url' ) === false ) {
        update_option( 'secure_embed_block_devtools_url', site_url( '/404' ) );
    }
    if ( get_option( 'secure_embed_input_width' ) === false ) {
        update_option( 'secure_embed_input_width', 400 );
    }
    if ( get_option( 'secure_embed_show_edit_delete' ) === false ) {
        update_option( 'secure_embed_show_edit_delete', 'on' );
    }
}

/* ============================================================
    2) ADMIN MENU SETUP
    ============================================================ */
/**
 * Adds the plugin's main menu and submenu pages to the WordPress admin.
 *
 * The main menu is titled "Secure Embed Manager" and includes submenus for database
 * management and plugin settings.
 */
add_action( 'admin_menu', 'secure_embed_add_admin_menu' );
function secure_embed_add_admin_menu() {
    add_menu_page(
        'Secure Embed Manager',        // Page title
        'Secure Embed Manager',        // Menu title
        'manage_options',              // Capability required
        'secure-embed',                // Menu slug
        'secure_embed_admin_page',     // Function to display the page
        'dashicons-admin-links',       // Icon
        20                             // Position
    );

    add_submenu_page(
        'secure-embed',                // Parent slug
        'Database Management',         // Page title
        'Database Management',         // Menu title
        'manage_options',              // Capability
        'secure-embed-db',             // Menu slug
        'secure_embed_db_page'         // Function to display the page
    );

    add_submenu_page(
        'secure-embed',
        'Plugin Settings',
        'Plugin Settings',
        'manage_options',
        'secure-embed-settings',
        'secure_embed_settings_page'
    );
}

/* ============================================================
    3) MAIN ADMIN PAGE: ADD, EDIT, DELETE, SEARCH, SORT
    ============================================================ */
/**
 * Renders the main admin page for managing videos.
 *
 * Provides a form for adding new video records, displays existing records with options
 * to edit, delete, search, and sort. All user inputs are sanitized.
 */
function secure_embed_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'secure_embed_videos';

    // Get user-defined input width option
    $fieldWidth = intval( get_option( 'secure_embed_input_width', 400 ) );
    $fieldStyle = "width: {$fieldWidth}px;";

    // Process form submission to add a new video record
    if ( isset( $_POST['add_link'] ) && check_admin_referer('secure_embed_add_video_action', 'secure_embed_add_video_nonce') ) {
        $db_name    = sanitize_text_field( $_POST['db_name'] );
        $embed_name = sanitize_text_field( $_POST['embed_name'] );
        $link       = esc_url_raw( $_POST['link'] );

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE link=%s", $link ) );
        if ( $exists ) {
            echo '<div class="notice notice-error is-dismissible"><p>This link already exists in the database.</p></div>';
        } elseif ( $db_name && $embed_name && $link ) {
            $unique_id = secure_embed_generate_unique_id( $wpdb, $table_name );
            $wpdb->insert( $table_name, [
                'db_name'    => $db_name,
                'embed_name' => $embed_name,
                'link'       => $link,
                'unique_id'  => $unique_id
            ] );
            echo '<div class="notice notice-success is-dismissible"><p>Video added successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Please fill out all fields.</p></div>';
        }
    }

    // Process deletion of a video record
    if ( isset( $_POST['delete_link'] ) && isset($_POST['id']) && check_admin_referer('secure_embed_delete_video_' . $_POST['id'], 'secure_embed_delete_video_nonce') ) {
        $del_id = intval( $_POST['id'] );
        $wpdb->delete( $table_name, ['id' => $del_id], ['%d'] );
        
        // Re-indexing IDs - This can be resource-intensive. Consider if truly necessary.
        // If gaps in IDs are acceptable, this section can be removed or commented out.
        $wpdb->query( "SET @count=0;" );
        $wpdb->query( "UPDATE $table_name SET id=(@count:=@count+1) ORDER BY id ASC;" );
        $max_id = $wpdb->get_var("SELECT MAX(id) FROM $table_name");
        $next_id = $max_id ? $max_id + 1 : 1; // Ensure next ID is correctly set even if table is empty
        $wpdb->query( "ALTER TABLE $table_name AUTO_INCREMENT=$next_id;" );
        echo '<div class="notice notice-success is-dismissible"><p>Video deleted and IDs reindexed.</p></div>';
    }

    // Process updating an existing record
    if ( isset( $_POST['update_record'] ) && isset($_POST['edit_id']) && check_admin_referer('secure_embed_edit_video_' . $_POST['edit_id'], 'secure_embed_edit_video_nonce') ) {
        $edit_id   = intval( $_POST['edit_id'] );
        $new_db    = sanitize_text_field( $_POST['edit_db_name'] );
        $new_embed = sanitize_text_field( $_POST['edit_embed_name'] );
        $new_link  = esc_url_raw( $_POST['edit_link'] );

        $wpdb->update( $table_name, [
            'db_name'    => $new_db,
            'embed_name' => $new_embed,
            'link'       => $new_link
        ], ['id' => $edit_id], ['%s', '%s', '%s'], ['%d'] );
        echo '<div class="notice notice-success is-dismissible"><p>Record updated for ID ' . esc_html($edit_id) . '.</p></div>';
    }

    // Retrieve a record for editing if the edit_id query parameter is set
    $edit_id = isset( $_GET['edit_id'] ) ? intval( $_GET['edit_id'] ) : 0;
    $edit_row = null;
    if ($edit_id > 0) {
        $edit_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id=%d", $edit_id ), ARRAY_A );
    }


    // Handle search and sort parameters
    $searchTerm = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
    $orderInput = isset( $_GET['order'] ) ? strtolower( $_GET['order'] ) : 'desc';
    $order = ( $orderInput === 'asc' ) ? 'asc' : 'desc';


    if ( $searchTerm !== '' ) {
        $like = '%' . $wpdb->esc_like($searchTerm) . '%';
        $videos = $wpdb->get_results( $wpdb->prepare( "
            SELECT * FROM $table_name
            WHERE (db_name LIKE %s OR embed_name LIKE %s OR link LIKE %s OR unique_id LIKE %s)
            ORDER BY id $order
        ", $like, $like, $like, $like ), ARRAY_A );
    } else {
        $videos = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id " . ($order === 'asc' ? 'ASC' : 'DESC'), ARRAY_A );
    }

    $nextOrder = ( $order === 'desc' ) ? 'asc' : 'desc';
    $sortLabel = ( $order === 'desc' ) ? 'Descending (current)' : 'Ascending (current)';
    $toggleLabel = ( $order === 'desc' ) ? 'Switch to Ascending' : 'Switch to Descending';
    $toggleUrl = add_query_arg( [
        'page'  => 'secure-embed',
        'order' => $nextOrder,
        's'     => $searchTerm
    ], admin_url( 'admin.php' ) );
    ?>
    <div class="wrap">
        <h1>Secure Embed Manager</h1>

        <form method="GET" style="margin-bottom:10px;">
            <input type="hidden" name="page" value="secure-embed">
            <input type="text" name="s" value="<?php echo esc_attr( $searchTerm ); ?>" placeholder="Search (name, link, ID)..." style="width:250px;">
            <input type="submit" class="button button-secondary" value="Search">
        </form>

        <p>
            <strong>Sort by ID:</strong> <?php echo esc_html( $sortLabel ); ?>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url( $toggleUrl ); ?>" class="button button-secondary"><?php echo esc_html( $toggleLabel ); ?></a>
        </p>

        <h2>Add a New Video</h2>
        <form method="POST" style="margin-bottom:20px;">
            <?php wp_nonce_field('secure_embed_add_video_action', 'secure_embed_add_video_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="db_name">Admin-Only Name</label></th>
                    <td><input type="text" id="db_name" name="db_name" style="<?php echo esc_attr( $fieldStyle ); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="embed_name">Embed Name (public)</label></th>
                    <td><input type="text" id="embed_name" name="embed_name" style="<?php echo esc_attr( $fieldStyle ); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="link">Real Video URL</label></th>
                    <td><input type="url" id="link" name="link" style="<?php echo esc_attr( $fieldStyle ); ?>" placeholder="https://..." required></td>
                </tr>
            </table>
            <?php submit_button( 'Add Video', 'primary', 'add_link' ); ?>
        </form>

        <?php if ( $edit_row ) : ?>
            <hr>
            <h2>Edit Record (ID <?php echo esc_html($edit_row['id']); ?>)</h2>
            <form method="POST">
                <?php wp_nonce_field('secure_embed_edit_video_' . $edit_row['id'], 'secure_embed_edit_video_nonce'); ?>
                <input type="hidden" name="edit_id" value="<?php echo esc_attr($edit_row['id']); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="edit_db_name">Admin-Only Name</label></th>
                        <td><input type="text" id="edit_db_name" name="edit_db_name" value="<?php echo esc_attr( $edit_row['db_name'] ); ?>" style="<?php echo esc_attr( $fieldStyle ); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="edit_embed_name">Embed Name (public)</label></th>
                        <td><input type="text" id="edit_embed_name" name="edit_embed_name" value="<?php echo esc_attr( $edit_row['embed_name'] ); ?>" style="<?php echo esc_attr( $fieldStyle ); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="edit_link">Real Video URL</label></th>
                        <td><input type="url" id="edit_link" name="edit_link" value="<?php echo esc_attr( $edit_row['link'] ); ?>" style="<?php echo esc_attr( $fieldStyle ); ?>" placeholder="https://..." required></td>
                    </tr>
                </table>
                <?php submit_button( 'Save Changes', 'primary', 'update_record' ); ?>
                <a class="button button-secondary" href="<?php echo esc_url( remove_query_arg( 'edit_id', add_query_arg( array('s' => $searchTerm, 'order' => $order ) ) ) ); ?>">Cancel</a>
            </form>
            <hr>
        <?php endif; ?>

        <h2>Existing Videos</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:5%;">ID</th>
                    <th>Admin Name (db_name)</th>
                    <th>Embed Name (public)</th>
                    <th>Real Link</th>
                    <th>Unique ID</th>
                    <th style="width:20%;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $videos ) ) : ?>
                <?php foreach ( $videos as $v ) : ?>
                <tr>
                    <td><?php echo esc_html($v['id']); ?></td>
                    <td><?php echo esc_html( $v['db_name'] ); ?></td>
                    <td><?php echo esc_html( $v['embed_name'] ); ?></td>
                    <td><a href="<?php echo esc_url( $v['link'] ); ?>" target="_blank" title="<?php echo esc_attr($v['link']); ?>"><?php echo esc_html( strlen($v['link']) > 50 ? substr($v['link'], 0, 47) . '...' : $v['link'] ); ?></a></td>
                    <td><?php echo esc_html( $v['unique_id'] ); ?></td>
                    <td>
                    <?php if ( get_option( 'secure_embed_show_edit_delete', 'on' ) === 'on' ) : ?>
                        <div class="delete-section" style="display:inline-block; margin-right: 5px;">
                            <form method="POST" style="display:inline-block; margin:0; padding:0;">
                                <?php wp_nonce_field('secure_embed_delete_video_' . $v['id'], 'secure_embed_delete_video_nonce'); ?>
                                <input type="hidden" name="id" value="<?php echo esc_attr($v['id']); ?>">
                                <button type="button" class="button button-small delete-init">Delete</button>
                                <input type="submit" name="delete_link" value="Confirm Delete" class="button button-small button-danger delete-confirm" style="display:none; margin-left:5px;">
                                <button type="button" class="button button-small button-secondary delete-cancel" style="display:none;">Cancel</button>
                            </form>
                        </div>

                        <?php
                            $editLink = add_query_arg( [
                                'page'    => 'secure-embed',
                                'edit_id' => $v['id'],
                                's'       => $searchTerm,
                                'order'   => $order
                            ], admin_url( 'admin.php' ) );
                        ?>
                        <div class="edit-section" style="display:inline-block;">
                             <a href="<?php echo esc_url($editLink); ?>" class="button button-small button-secondary">Edit</a>
                        </div>
                    <?php endif; ?>
                        <button class="button button-small button-primary copy-embed-code-btn" style="margin-left:5px;" data-embed-name="<?php echo esc_attr( $v['embed_name'] ); ?>" data-unique-id="<?php echo esc_attr( $v['unique_id'] ); ?>">Copy Embed</button>
                        <span class="copy-notice" style="color:green; margin-left:5px; visibility:hidden; font-size:0.9em;"></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="6">No videos found<?php echo $searchTerm ? ' for your search query.' : '.'; ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        // Copy Embed Code
        document.querySelectorAll('.copy-embed-code-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                const embedName = this.dataset.embedName;
                const uniqueId = this.dataset.uniqueId;
                const snippet = `<div class="video-container">\n    <a href="#" class="video-toggler" data-id="${uniqueId}">${embedName}</a>\n    <div class="video-content" style="display:none;">\n        <iframe src="about:blank" frameborder="0" width="640" height="360" allowfullscreen></iframe>\n    </div>\n</div>`;
                navigator.clipboard.writeText(snippet).then(() => {
                    const noticeSpan = this.parentElement.querySelector('.copy-notice');
                    if (noticeSpan) {
                        noticeSpan.textContent = 'Copied!';
                        noticeSpan.style.visibility = 'visible';
                        setTimeout(() => { noticeSpan.style.visibility = 'hidden'; }, 2000);
                    }
                }).catch(e => { 
                    console.error('Failed to copy embed code:', e); 
                    // Fallback for browsers that might restrict clipboard API in certain contexts (e.g. iframes without permission)
                });
            });
        });

        // DELETE confirmation handlers
        document.querySelectorAll('.delete-init').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                const form = btn.closest('form');
                if (!form) return;
                btn.style.display = 'none';
                const confirmBtn = form.querySelector('.delete-confirm');
                const cancelBtn = form.querySelector('.delete-cancel');
                if (confirmBtn) confirmBtn.style.display = 'inline-block';
                if (cancelBtn) cancelBtn.style.display = 'inline-block';
            });
        });
        document.querySelectorAll('.delete-cancel').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                const form = btn.closest('form');
                if (!form) return;
                const initBtn = form.querySelector('.delete-init');
                const confirmBtn = form.querySelector('.delete-confirm');
                if (initBtn) initBtn.style.display = 'inline-block';
                if (confirmBtn) confirmBtn.style.display = 'none';
                btn.style.display = 'none';
            });
        });
    });
    </script>
    <?php
}

/* ============================================================
    4) GENERATE UNIQUE VIDEO RECORD ID
    ============================================================ */
/**
 * Generates a unique identifier for a video record.
 *
 * This function repeatedly generates a candidate ID until a unique one is found in the database.
 *
 * @param WPDB $wpdb     The global WPDB object.
 * @param string $table_name The name of the table to check.
 * @return string          The unique identifier.
 */
function secure_embed_generate_unique_id( $wpdb, $table_name ) {
    do {
        $candidate = 'vid_' . wp_generate_password( 10, false, false );
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE unique_id=%s", $candidate ) );
    } while ( $count > 0 );
    return $candidate;
}

/* ============================================================
    5) DATABASE MANAGEMENT PAGE
    ============================================================ */
/**
 * Renders the database management page.
 *
 * Provides options to download a JSON backup, restore the database from a JSON file,
 * reset the database, and import records via CSV.
 */
function secure_embed_db_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'secure_embed_videos';
    $restore_confirmation_text = 'I know this is dangerous and I will lose my current database';


    // Download database as JSON
    if ( isset( $_POST['download_db'] ) && check_admin_referer('secure_embed_db_actions', 'secure_embed_nonce') ) {
        $records = $wpdb->get_results( "SELECT id, db_name, embed_name, link, unique_id FROM $table_name", ARRAY_A );
        $jsonStr = json_encode( $records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="secure-embed-backup-' . date('Y-m-d_H-i-s') . '.json"' );
        header( 'Content-Length: ' . strlen( $jsonStr ) );
        echo $jsonStr;
        exit;
    }

    // Restore database from JSON upload
    if ( isset( $_POST['upload_db'] ) && isset($_POST['confirm_restore']) && $_POST['confirm_restore'] === $restore_confirmation_text && check_admin_referer('secure_embed_db_actions', 'secure_embed_nonce') ) {
        if ( ! empty( $_FILES['db_file']['tmp_name'] ) && $_FILES['db_file']['error'] === UPLOAD_ERR_OK ) {
            $file_info = wp_check_filetype(basename($_FILES['db_file']['name']));
            if (strtolower($file_info['ext']) === 'json') {
                $data = file_get_contents( $_FILES['db_file']['tmp_name'] );
                $arr = json_decode( $data, true );
                if ( is_array( $arr ) ) {
                    $wpdb->query( "TRUNCATE TABLE $table_name" );
                    $imported_count = 0;
                    $skipped_count = 0;
                    foreach ( $arr as $row ) {
                        if ( isset($row['db_name'], $row['embed_name'], $row['link'], $row['unique_id']) && !empty($row['link']) && !empty($row['unique_id']) ) {
                            $db_name = sanitize_text_field( $row['db_name'] );
                            $embed_n = sanitize_text_field( $row['embed_name'] );
                            $link    = esc_url_raw( $row['link'] );
                            $uniq    = sanitize_text_field( $row['unique_id'] );

                            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE unique_id=%s", $uniq ) );
                            if ( $exists ) { 
                                $uniq = secure_embed_generate_unique_id( $wpdb, $table_name );
                            }
                            $wpdb->insert( $table_name, [
                                'db_name'    => $db_name,
                                'embed_name' => $embed_n,
                                'link'       => $link,
                                'unique_id'  => $uniq
                            ] );
                            $imported_count++;
                        } else {
                            $skipped_count++;
                        }
                    }
                    echo '<div class="notice notice-success is-dismissible"><p>Database restored! Imported ' . $imported_count . ' records. Skipped ' . $skipped_count . ' invalid/incomplete records.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Invalid JSON file format or empty file.</p></div>';
                }
            } else {
                 echo '<div class="notice notice-error is-dismissible"><p>Invalid file type. Please upload a .json file.</p></div>';
            }
        } else {
            $upload_error_message = 'No file uploaded or an error occurred during upload.';
            if(isset($_FILES['db_file']['error']) && $_FILES['db_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_error_message .= ' (Error Code: ' . $_FILES['db_file']['error'] . ')';
            }
            echo '<div class="notice notice-error is-dismissible"><p>' . $upload_error_message . '</p></div>';
        }
    } elseif (isset( $_POST['upload_db'] ) && (!isset($_POST['confirm_restore']) || $_POST['confirm_restore'] !== $restore_confirmation_text )) {
        echo '<div class="notice notice-error is-dismissible"><p>Restore confirmation incorrect. Database not restored.</p></div>';
    }


    // Reset database
    if ( isset( $_POST['reset_db'] ) && isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === $restore_confirmation_text && check_admin_referer('secure_embed_db_actions', 'secure_embed_nonce') ) {
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
        secure_embed_create_table();
        echo '<div class="notice notice-success is-dismissible"><p>Database has been reset. All records deleted and table recreated.</p></div>';
    } elseif (isset( $_POST['reset_db'] ) && (!isset($_POST['confirm_reset']) || $_POST['confirm_reset'] !== $restore_confirmation_text )) {
        echo '<div class="notice notice-error is-dismissible"><p>Reset confirmation incorrect. Database not reset.</p></div>';
    }


    // CSV Import
    if ( isset( $_POST['import_csv'] ) && check_admin_referer('secure_embed_db_actions', 'secure_embed_nonce') ) {
        if ( ! empty( $_FILES['csv_file']['tmp_name'] ) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK ) {
             $file_info = wp_check_filetype(basename($_FILES['csv_file']['name']));
             if (strtolower($file_info['ext']) === 'csv') {
                $handle = @fopen( $_FILES['csv_file']['tmp_name'], 'r' ); 
                if ( $handle ) {
                    $count_imported = 0;
                    $count_skipped_existing_link = 0;
                    $count_skipped_invalid_row = 0;
                    $is_header_skipped = false;

                    while ( ( $line = fgetcsv( $handle ) ) !== false ) {
                        if (!$is_header_skipped) { 
                            $is_header_skipped = true;
                            continue;
                        }
                        if ( count( $line ) >= 3 ) {
                            $db_name = sanitize_text_field( trim($line[0]) );
                            $embed_n = sanitize_text_field( trim($line[1]) );
                            $link    = esc_url_raw( trim($line[2]) );

                            if (empty($link) || empty($db_name) || empty($embed_n)) {
                                $count_skipped_invalid_row++;
                                continue;
                            }
                            $exists_link  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE link=%s", $link ) );
                            if ( ! $exists_link ) {
                                $uniq = secure_embed_generate_unique_id( $wpdb, $table_name );
                                $wpdb->insert( $table_name, [
                                    'db_name'    => $db_name,
                                    'embed_name' => $embed_n,
                                    'link'       => $link,
                                    'unique_id'  => $uniq
                                ] );
                                $count_imported++;
                            } else {
                                $count_skipped_existing_link++;
                            }
                        } else {
                            $count_skipped_invalid_row++;
                        }
                    }
                    fclose( $handle );
                    echo '<div class="notice notice-success is-dismissible"><p>CSV Import complete: Imported ' . $count_imported . ' new video(s). Skipped ' . $count_skipped_existing_link . ' due to existing links, and ' . $count_skipped_invalid_row . ' invalid/incomplete rows.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Could not open CSV file for reading.</p></div>';
                }
            } else {
                 echo '<div class="notice notice-error is-dismissible"><p>Invalid file type. Please upload a .csv file.</p></div>';
            }
        } else {
            $upload_error_message = 'No CSV file uploaded or an error occurred during upload.';
            if(isset($_FILES['csv_file']['error']) && $_FILES['csv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_error_message .= ' (Error Code: ' . $_FILES['csv_file']['error'] . ')';
            }
            echo '<div class="notice notice-error is-dismissible"><p>' . $upload_error_message . '</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Database Management</h1>
        <form method="POST">
            <?php wp_nonce_field('secure_embed_db_actions', 'secure_embed_nonce'); ?>
            <?php submit_button( 'Download Database as JSON', 'secondary', 'download_db' ); ?>
        </form>
        <hr>
        <h3>Restore Database (Upload JSON)</h3>
        <p><strong>Warning:</strong> This will overwrite your existing video records. Type <code><?php echo esc_html($restore_confirmation_text); ?></code> below to confirm.</p>
        <form method="POST" enctype="multipart/form-data">
            <?php wp_nonce_field('secure_embed_db_actions', 'secure_embed_nonce'); ?>
            <p><label for="db_file_upload">Select JSON file:</label><br>
            <input type="file" id="db_file_upload" name="db_file" accept=".json" required></p>
            <p><label for="confirm_restore_text">Confirm action:</label><br>
            <input type="text" id="confirm_restore_text" name="confirm_restore" placeholder="<?php echo esc_attr($restore_confirmation_text); ?>" style="width: 400px;" required></p>
            <?php submit_button( 'Restore Database from JSON', 'secondary', 'upload_db' ); ?>
        </form>
        <hr>
        <h3>Reset Database</h3>
        <p><strong>Warning:</strong> This will delete all your video records. Type <code><?php echo esc_html($restore_confirmation_text); ?></code> below to confirm.</p>
        <form method="POST">
            <?php wp_nonce_field('secure_embed_db_actions', 'secure_embed_nonce'); ?>
            <p><label for="confirm_reset_text">Confirm action:</label><br>
            <input type="text" id="confirm_reset_text" name="confirm_reset" placeholder="<?php echo esc_attr($restore_confirmation_text); ?>" style="width: 400px;" required></p>
            <?php submit_button( 'Reset Database', 'delete', 'reset_db' ); ?>
        </form>
        <hr>
        <h3>Bulk Import (CSV)</h3>
        <p>Format: <code>Admin-Only Name, Embed Name (public), Real Video URL</code> (one record per line). The first line is assumed to be a header and will be skipped.</p>
        <form method="POST" enctype="multipart/form-data">
            <?php wp_nonce_field('secure_embed_db_actions', 'secure_embed_nonce'); ?>
            <p><label for="csv_file_upload">Select CSV file:</label><br>
            <input type="file" id="csv_file_upload" name="csv_file" accept=".csv,text/csv" required></p>
            <?php submit_button( 'Import CSV', 'primary', 'import_csv' ); ?>
        </form>
    </div>
    <?php
}

/* ============================================================
    6) PLUGIN SETTINGS PAGE
    ============================================================ */
/**
 * Renders the plugin settings page.
 *
 * Allows administrators to configure DevTools blocking, the redirect URL,
 * the input field width, and whether to show the edit/delete buttons.
 */
function secure_embed_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['secure_embed_settings_nonce'] ) && wp_verify_nonce( $_POST['secure_embed_settings_nonce'], 'secure_embed_save_settings' ) ) {
        if ( isset( $_POST['secure_embed_block_devtools'] ) ) {
            $val = ( $_POST['secure_embed_block_devtools'] === 'on' ) ? 'on' : 'off';
            update_option( 'secure_embed_block_devtools', $val );
            echo '<div class="notice notice-success is-dismissible"><p>DevTools blocking setting saved: <strong>' . esc_html($val) . '</strong>.</p></div>';
        }

        if ( isset( $_POST['secure_embed_block_devtools_url'] ) ) {
            $u = esc_url_raw( sanitize_text_field( $_POST['secure_embed_block_devtools_url'] ) );
            if (empty($u) || !filter_var($u, FILTER_VALIDATE_URL)) {
                $u_default = site_url('/404'); 
                update_option( 'secure_embed_block_devtools_url', $u_default );
                 echo '<div class="notice notice-warning is-dismissible"><p>Invalid redirect URL provided. Defaulted to: <strong>' . esc_html($u_default) . '</strong>.</p></div>';
            } else {
                update_option( 'secure_embed_block_devtools_url', $u );
                echo '<div class="notice notice-success is-dismissible"><p>DevTools redirect URL saved: <strong>' . esc_html($u) . '</strong>.</p></div>';
            }
        }

        if ( isset( $_POST['secure_embed_input_width'] ) ) {
            $w = absint( $_POST['secure_embed_input_width'] );
            $w = max(50, min(2000, $w)); 
            update_option( 'secure_embed_input_width', $w );
            echo '<div class="notice notice-success is-dismissible"><p>Field width setting saved: <strong>' . esc_html($w) . 'px</strong>.</p></div>';
        }

        if ( isset( $_POST['secure_embed_show_edit_delete'] ) ) {
            $editdel = ( $_POST['secure_embed_show_edit_delete'] === 'on' ) ? 'on' : 'off';
            update_option( 'secure_embed_show_edit_delete', $editdel );
            echo '<div class="notice notice-success is-dismissible"><p>Show Edit/Delete Buttons setting saved: <strong>' . esc_html($editdel) . '</strong>.</p></div>';
        }
    }


    $toggle   = get_option( 'secure_embed_block_devtools', 'off' );
    $redirect = get_option( 'secure_embed_block_devtools_url', site_url( '/404' ) );
    $wVal     = get_option( 'secure_embed_input_width', 400 );
    $ed       = get_option( 'secure_embed_show_edit_delete', 'on' );
    ?>
    <div class="wrap">
        <h1>Plugin Settings</h1>
        <form method="POST">
            <?php wp_nonce_field( 'secure_embed_save_settings', 'secure_embed_settings_nonce' ); ?>
            <h3>DevTools Blocking</h3>
            <fieldset>
                <legend class="screen-reader-text">DevTools Blocking</legend>
                <label>
                    <input type="radio" name="secure_embed_block_devtools" value="off" <?php checked( $toggle, 'off' ); ?>>
                    Off
                </label>
                <br>
                <label>
                    <input type="radio" name="secure_embed_block_devtools" value="on" <?php checked( $toggle, 'on' ); ?>>
                    On (Redirects users with open DevTools)
                </label>
            </fieldset>
            <hr>
            <h3>DevTools Redirect URL</h3>
            <p>URL to redirect to if DevTools is detected (e.g., your site's 404 page or a custom warning page). Must be a full URL (e.g., <code>https://example.com/warning</code>).</p>
            <label for="secure_embed_block_devtools_url_field">Redirect URL:</label>
            <input type="url" id="secure_embed_block_devtools_url_field" name="secure_embed_block_devtools_url" value="<?php echo esc_attr( $redirect ); ?>" style="width:400px;" placeholder="<?php echo esc_attr(site_url('/404')); ?>" required>
            <br><br>
            <hr>
            <h3>Admin Input Field Width (pixels)</h3>
            <p>Width for text input fields on the 'Secure Embed Manager' page. Default 400, range 50-2000.</p>
            <label for="secure_embed_input_width_field">Field width:</label>
            <input type="number" id="secure_embed_input_width_field" name="secure_embed_input_width" min="50" max="2000" step="10" value="<?php echo esc_attr( $wVal ); ?>">
            <hr>
            <h3>Show Edit/Delete Buttons</h3>
            <p>Control visibility of Edit/Delete buttons in the 'Existing Videos' table.</p>
             <fieldset>
                <legend class="screen-reader-text">Show Edit/Delete Buttons</legend>
                <label>
                    <input type="radio" name="secure_embed_show_edit_delete" value="off" <?php checked( $ed, 'off' ); ?>>
                    Off (Hide Edit & Delete buttons)
                </label>
                <br>
                <label>
                    <input type="radio" name="secure_embed_show_edit_delete" value="on" <?php checked( $ed, 'on' ); ?>>
                    On (Show Edit & Delete buttons)
                </label>
            </fieldset>
            <br><br>
            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>
    <?php
}

/* ============================================================
    7) AJAX: GET VIDEO URL
    ============================================================ */
/**
 * Handles AJAX requests to retrieve the real video URL based on a unique identifier.
 *
 * The function checks for a provided unique ID, queries the database for the associated
 * video URL, and returns the result as JSON.
 */
add_action( 'wp_ajax_get_video_url', 'secure_embed_ajax_fetch_link' );
add_action( 'wp_ajax_nopriv_get_video_url', 'secure_embed_ajax_fetch_link' );
function secure_embed_ajax_fetch_link() {
    // Nonce for AJAX can be added here for better security if needed.
    // $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    // if (!wp_verify_nonce($nonce, 'secure_embed_video_load_nonce')) {
    //     wp_send_json_error(['message' => 'Nonce verification failed.'], 403);
    //     return;
    // }

    $unique_id = isset( $_POST['unique_id'] ) ? sanitize_text_field( $_POST['unique_id'] ) : '';
    if ( ! $unique_id ) {
        wp_send_json_error( [ 'message' => 'No unique ID provided' ], 400 );
        return;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'secure_embed_videos';
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT link FROM $table_name WHERE unique_id=%s", $unique_id ), ARRAY_A );

    if ( $row && ! empty( $row['link'] ) ) {
        wp_send_json_success( [ 'url' => $row['link'] ] );
    } else {
        wp_send_json_error( [ 'message' => 'Video not found or link is empty' ], 404 );
    }
}

/* ============================================================
    8) FRONT‑END SCRIPT + IMPROVED DEVTOOLS BLOCKING
    ============================================================ */
/**
 * Enqueues the front‑end scripts.
 *
 * Registers and enqueues the main JavaScript for toggling video iframes via AJAX.
 * If DevTools blocking is enabled in the settings, this function injects the obfuscated
 * JavaScript for detecting open DevTools.
 */
add_action( 'wp_enqueue_scripts', 'secure_embed_enqueue_frontend_script' );
function secure_embed_enqueue_frontend_script() {
    $ajax_url = admin_url( 'admin-ajax.php' );
    // $video_load_nonce_value = wp_create_nonce('secure_embed_video_load_nonce'); // Example: Define if using AJAX nonce

    $main_script = <<<JS
document.addEventListener('DOMContentLoaded', function(){
    var toggles = document.querySelectorAll('.video-toggler');
    // var videoLoadNonce = 'VIDEO_LOAD_NONCE_PLACEHOLDER'; // If using nonce, ensure PHP variable is defined or use placeholder.

    toggles.forEach(function(toggler){
        toggler.addEventListener('click', function(e){
            e.preventDefault();
            var uniqueID = toggler.getAttribute('data-id');
            var container = toggler.closest('.video-container');
            if (!container) { console.error('SecureEmbed: Video container not found for ID:', uniqueID); return; }
            var iframe = container.querySelector('iframe');
            var contentDiv = container.querySelector('.video-content');
            if (!iframe || !contentDiv) { console.error('SecureEmbed: Iframe or content div not found for ID:', uniqueID); return; }

            var isCurrentlyVisible = contentDiv.style.display !== 'none' && contentDiv.style.display !== '';

            if(!isCurrentlyVisible){
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '{$ajax_url}', true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                
                var params = 'action=get_video_url&unique_id=' + encodeURIComponent(uniqueID);
                // if (typeof videoLoadNonce !== 'undefined' && videoLoadNonce !== 'VIDEO_LOAD_NONCE_PLACEHOLDER') { 
                //    params += '&nonce=' + encodeURIComponent(videoLoadNonce); 
                // }


                xhr.onload = function(){
                    if(xhr.status === 200){
                        try{
                            var res = JSON.parse(xhr.responseText);
                            if(res.success && res.data && res.data.url){
                                iframe.src = res.data.url;
                                contentDiv.style.display = 'block';
                            } else {
                                var errorMsg = res.data && res.data.message ? res.data.message : 'Unknown AJAX error';
                                console.error('SecureEmbed: Failed to get video URL -', errorMsg, 'ID:', uniqueID);
                                contentDiv.innerHTML = '<p style="color:red;">Error: Could not load video (' + errorMsg + ').</p>';
                                contentDiv.style.display = 'block';
                            }
                        } catch(parseError){
                            console.error('SecureEmbed: Error parsing AJAX response -', parseError, xhr.responseText, 'ID:', uniqueID);
                            contentDiv.innerHTML = '<p style="color:red;">Error: Invalid response from server.</p>';
                            contentDiv.style.display = 'block';
                        }
                    } else {
                        console.error('SecureEmbed: AJAX request failed with status:', xhr.status, 'ID:', uniqueID);
                        contentDiv.innerHTML = '<p style="color:red;">Error: Server request failed (Status: ' + xhr.status + ').</p>';
                        contentDiv.style.display = 'block';
                    }
                };
                xhr.onerror = function() {
                    console.error('SecureEmbed: AJAX request network error. ID:', uniqueID);
                    contentDiv.innerHTML = '<p style="color:red;">Error: Network problem prevented video loading.</p>';
                    contentDiv.style.display = 'block';
                };
                xhr.send(params);
            } else {
                contentDiv.style.display = 'none';
                iframe.src = 'about:blank';
            }
        });
    });
});
JS;

    // Use the SECURE_EMBED_VERSION constant defined at the top of the file
    wp_register_script( 'secure-embed-front', '', [], SECURE_EMBED_VERSION, true );
    wp_enqueue_script( 'secure-embed-front' );
    wp_add_inline_script( 'secure-embed-front', $main_script );

// --- Begin DevTools Blocking Code ---
$toggle = get_option('secure_embed_block_devtools', 'off');
if ($toggle === 'on') {
    $block_url = esc_url_raw(get_option('secure_embed_block_devtools_url', site_url('/404')));    // NOTE: Enhanced DevTools blocking - non-obfuscated version for reference
    // (function(){
    //     localStorage.removeItem('se_dt_op');
    //     sessionStorage.removeItem('se_dt_session');
    //     
    //     const startTime = performance.now();
    //     let redirected = false;
    //     let lastRedirectAttempt = 0;
    //     const redirectCooldown = 1500;
    //     let resizeDebounceTimer;
    //     let detectionCount = 0;
    //     const maxDetections = 3;
    //     
    //     // Enhanced redirect with multiple fallbacks
    //     function doRedirect() {
    //         if (redirected && (performance.now() - lastRedirectAttempt < redirectCooldown)) return;
    //         redirected = true;
    //         lastRedirectAttempt = performance.now();
    //         detectionCount++;
    //         
    //         localStorage.setItem('se_dt_op', 'true');
    //         sessionStorage.setItem('se_dt_session', Date.now().toString());
    //         
    //         // Multiple redirect methods for better reliability
    //         try { 
    //             document.body.style.display = 'none';
    //             document.documentElement.innerHTML = '<h1>Access Denied</h1>';
    //         } catch(e) {}
    //         
    //         const redirectMethods = [
    //             () => window.location.replace('BLOCK_URL'),
    //             () => window.location.href = 'BLOCK_URL',
    //             () => window.location.assign('BLOCK_URL'),
    //             () => history.replaceState(null, '', 'BLOCK_URL')
    //         ];
    //         
    //         redirectMethods.forEach((method, i) => {
    //             setTimeout(method, i * 100);
    //         });
    //     }
    //     
    //     function safeRedirect() {
    //         if (document.hidden || !document.hasFocus() || detectionCount >= maxDetections) return;
    //         doRedirect();
    //     }
    //     
    //     // Enhanced Detector 1: Multiple debugger timing checks with variable thresholds
    //     function checkDebugger() {
    //         const thresholds = [100, 150, 200];
    //         for (let threshold of thresholds) {
    //             const start = performance.now();
    //             debugger;
    //             const end = performance.now();
    //             if ((end - start) > threshold) {
    //                 safeRedirect();
    //                 return;
    //             }
    //         }
    //     }
    //     
    //     // Enhanced Detector 2: Extended keyboard shortcuts with right-click protection
    //     function keyHandler(e) {
    //         const blocked = e.keyCode === 123 || // F12
    //             (e.ctrlKey && e.shiftKey && [73, 74, 67, 75].includes(e.keyCode)) || // Ctrl+Shift+I/J/C/K
    //             (e.metaKey && e.altKey && [73, 74, 67].includes(e.keyCode)) || // Cmd+Alt+I/J/C
    //             (e.ctrlKey && [85, 83].includes(e.keyCode)) || // Ctrl+U/S
    //             (e.keyCode === 116 && (e.ctrlKey || e.metaKey)); // Ctrl/Cmd+F5
    //         
    //         if (blocked) {
    //             e.preventDefault();
    //             e.stopPropagation();
    //             safeRedirect();
    //         }
    //     }
    //     
    //     // Context menu blocking
    //     function contextHandler(e) {
    //         e.preventDefault();
    //         e.stopPropagation();
    //         safeRedirect();
    //         return false;
    //     }
    //     
    //     window.addEventListener('keydown', keyHandler, true);
    //     document.addEventListener('contextmenu', contextHandler, true);
    //     
    //     // Enhanced Detector 3: Performance monitoring with adaptive thresholds
    //     const frameTimeoutThreshold = 2500;
    //     let currentLastTimestamp = startTime;
    //     let performanceWarnings = 0;
    //     
    //     function checkFrame() {
    //         const now = performance.now();
    //         const timeDiff = now - currentLastTimestamp;
    //         
    //         if (document.hasFocus() && !document.hidden) {
    //             if (timeDiff > frameTimeoutThreshold) {
    //                 performanceWarnings++;
    //                 if (performanceWarnings > 2) safeRedirect();
    //             } else {
    //                 performanceWarnings = Math.max(0, performanceWarnings - 1);
    //             }
    //         }
    //         
    //         currentLastTimestamp = now;
    //         if (typeof requestAnimationFrame === 'function') {
    //             requestAnimationFrame(checkFrame);
    //         }
    //     }
    //     
    //     if (typeof requestAnimationFrame === 'function') {
    //         requestAnimationFrame(checkFrame);
    //     }
    //     
    //     // Enhanced Detector 4: Advanced dimension detection with browser-specific handling
    //     function checkDimensions() {
    //         const ua = navigator.userAgent.toLowerCase();
    //         const isFirefox = ua.includes('firefox');
    //         const isChrome = ua.includes('chrome');
    //         const isSafari = ua.includes('safari') && !isChrome;
    //         const isEdge = ua.includes('edge') || ua.includes('edg/');
    //         
    //         let threshold = 160;
    //         if (isFirefox) threshold = 140;
    //         if (isSafari) threshold = 180;
    //         if (isEdge) threshold = 150;
    //         
    //         const widthDiff = window.outerWidth - window.innerWidth;
    //         const heightDiff = window.outerHeight - window.innerHeight;
    //         
    //         const isDockedDevTools = widthDiff > threshold || heightDiff > threshold;
    //         
    //         // Browser-specific normal differences
    //         let isNormalDifference = false;
    //         if (isFirefox && widthDiff < 70 && heightDiff < 100) isNormalDifference = true;
    //         if (isChrome && widthDiff < 20 && heightDiff < 90) isNormalDifference = true;
    //         if (isSafari && widthDiff < 15 && heightDiff < 80) isNormalDifference = true;
    //         
    //         // Additional check for undocked DevTools (separate window)
    //         const screenRatio = screen.availHeight / screen.availWidth;
    //         const windowRatio = window.innerHeight / window.innerWidth;
    //         const ratioDeviation = Math.abs(screenRatio - windowRatio);
    //         
    //         if ((isDockedDevTools && !isNormalDifference) || ratioDeviation > 0.5) {
    //             safeRedirect();
    //         }
    //     }
    //     
    //     // Enhanced Detector 5: Comprehensive console integrity and function hijacking detection
    //     function checkConsoleIntegrity() {
    //         try {
    //             const consoleMethods = ['log', 'warn', 'error', 'info', 'debug', 'clear', 'dir', 'table'];
    //             
    //             for (let method of consoleMethods) {
    //                 if (console[method] && typeof console[method].toString === 'function') {
    //                     const methodStr = console[method].toString();
    //                     if (!methodStr.includes('[native code]') && !methodStr.includes('[Command Line API]')) {
    //                         safeRedirect();
    //                         return;
    //                     }
    //                 }
    //             }
    //             
    //             // Check for common function hijacking
    //             const originalFunctions = [
    //                 'setTimeout', 'setInterval', 'clearTimeout', 'clearInterval',
    //                 'addEventListener', 'removeEventListener'
    //             ];
    //             
    //             for (let func of originalFunctions) {
    //                 if (window[func] && typeof window[func].toString === 'function') {
    //                     const funcStr = window[func].toString();
    //                     if (!funcStr.includes('[native code]')) {
    //                         safeRedirect();
    //                         return;
    //                     }
    //                 }
    //             }
    //             
    //             // Check for DevTools-specific globals
    //             const devToolsGlobals = ['__REACT_DEVTOOLS_GLOBAL_HOOK__', '__VUE_DEVTOOLS_GLOBAL_HOOK__'];
    //             for (let global of devToolsGlobals) {
    //                 if (window[global]) {
    //                     safeRedirect();
    //                     return;
    //                 }
    //             }
    //             
    //         } catch (e) {
    //             // If we can't check console integrity, that might be suspicious too
    //             safeRedirect();
    //         }
    //     }
    //     
    //     // New Detector 6: Network timing anomalies
    //     function checkNetworkTiming() {
    //         const img = new Image();
    //         const start = performance.now();
    //         img.onerror = img.onload = function() {
    //             const loadTime = performance.now() - start;
    //             // If load time is suspiciously fast, might indicate request interception
    //             if (loadTime < 1) {
    //                 safeRedirect();
    //             }
    //         };
    //         img.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7?' + Math.random();
    //     }
    //     
    //     // New Detector 7: Element inspection protection
    //     function protectElements() {
    //         const observer = new MutationObserver(function(mutations) {
    //             mutations.forEach(function(mutation) {
    //                 if (mutation.type === 'attributes' && 
    //                     (mutation.attributeName === 'class' || mutation.attributeName === 'style')) {
    //                     // Check if element styling suggests DevTools inspection
    //                     const target = mutation.target;
    //                     if (target.style && target.style.outline && target.style.outline.includes('2px solid')) {
    //                         safeRedirect();
    //                     }
    //                 }
    //             });
    //         });
    //         
    //         observer.observe(document.body, {
    //             attributes: true,
    //             subtree: true,
    //             attributeFilter: ['class', 'style']
    //         });
    //     }
    //     
    //     // Enhanced resize handling with momentum detection
    //     let resizeCount = 0;
    //     let lastResizeTime = 0;
    //     
    //     window.addEventListener('resize', function() {
    //         const now = Date.now();
    //         const timeSinceLastResize = now - lastResizeTime;
    //         
    //         if (timeSinceLastResize < 100) {
    //             resizeCount++;
    //             if (resizeCount > 5) {
    //                 safeRedirect();
    //                 return;
    //             }
    //         } else {
    //             resizeCount = 0;
    //         }
    //         
    //         lastResizeTime = now;
    //         clearTimeout(resizeDebounceTimer);
    //         resizeDebounceTimer = setTimeout(function() {
    //             if (document.hasFocus() && !document.hidden) {
    //                 checkDimensions();
    //             }
    //         }, 200);
    //     });
    //     
    //     // Initialize element protection
    //     if (document.readyState === 'loading') {
    //         document.addEventListener('DOMContentLoaded', protectElements);
    //     } else {
    //         protectElements();
    //     }
    //     
    //     // Randomized periodic checks to avoid pattern detection
    //     function randomizedChecks() {
    //         if (document.hasFocus() && !document.hidden && detectionCount < maxDetections) {
    //             const checks = [checkDebugger, checkDimensions, checkConsoleIntegrity, checkNetworkTiming];
    //             const randomCheck = checks[Math.floor(Math.random() * checks.length)];
    //             randomCheck();
    //         }
    //         
    //         // Randomize next check interval between 500-1200ms
    //         const nextInterval = 500 + Math.random() * 700;
    //         setTimeout(randomizedChecks, nextInterval);
    //     }
    //     
    //     // Start randomized checks
    //     setTimeout(randomizedChecks, 1000);
    //     
    //     // Legacy periodic interval as fallback
    //     const periodicInterval = setInterval(function() {
    //         if (document.hasFocus() && !document.hidden && detectionCount < maxDetections) {
    //             checkDebugger();
    //             checkConsoleIntegrity();
    //         }
    //     }, 800);
    //     
    //     // Cleanup on page unload
    //     window.addEventListener('beforeunload', function() {
    //         clearInterval(periodicInterval);
    //         clearTimeout(resizeDebounceTimer);
    //     });
    // })();    // Enhanced DevTools Blocking - Clean Unobfuscated Version
    $devtools_js = <<<'JS'
function _0x24b9(_0x4ed9d3,_0x1e4d07){var _0x42540b=_0x4254();return _0x24b9=function(_0x24b9b3,_0x4daef3){_0x24b9b3=_0x24b9b3-0x170;var _0x9e6b6c=_0x42540b[_0x24b9b3];return _0x9e6b6c;},_0x24b9(_0x4ed9d3,_0x1e4d07);}(function(_0x22f2b1,_0x3f0edd){var _0x436728=_0x24b9,_0x15b4b8=_0x22f2b1();while(!![]){try{var _0x203618=parseInt(_0x436728(0x17f))/0x1+-parseInt(_0x436728(0x173))/0x2+parseInt(_0x436728(0x189))/0x3+-parseInt(_0x436728(0x1a2))/0x4*(-parseInt(_0x436728(0x188))/0x5)+-parseInt(_0x436728(0x178))/0x6+-parseInt(_0x436728(0x1a0))/0x7*(parseInt(_0x436728(0x17e))/0x8)+parseInt(_0x436728(0x196))/0x9*(parseInt(_0x436728(0x18c))/0xa);if(_0x203618===_0x3f0edd)break;else _0x15b4b8['push'](_0x15b4b8['shift']());}catch(_0x3d9769){_0x15b4b8['push'](_0x15b4b8['shift']());}}}(_0x4254,0xc7724),(function(){'use strict';var _0x36479b=_0x24b9;var _0x4241b0=performance[_0x36479b(0x1c1)](),_0x1e58e5=![],_0xc3b15a=0x0,_0x2f5e5c=0x5dc,_0x2f941b,_0x1103f4=0x0,_0x1f7a4a=0x3,_0x219196=0x0,_0x23774f=0x0;try{localStorage[_0x36479b(0x191)](_0x36479b(0x186)),sessionStorage['removeItem']('se_dt_session');}catch(_0x4394d8){}function _0x5686c5(){var _0x294bc1=_0x36479b;if(_0x1e58e5&&performance['now']()-_0xc3b15a<_0x2f5e5c)return;_0x1e58e5=!![],_0xc3b15a=performance[_0x294bc1(0x1c1)](),_0x1103f4++;try{localStorage[_0x294bc1(0x175)]('se_dt_open',_0x294bc1(0x172)),sessionStorage[_0x294bc1(0x175)](_0x294bc1(0x1a7),Date['now']()['toString']());}catch(_0x337700){}try{document[_0x294bc1(0x1bb)][_0x294bc1(0x19b)][_0x294bc1(0x1bc)]='none',document[_0x294bc1(0x1a5)][_0x294bc1(0x194)]=_0x294bc1(0x180);}catch(_0x15d087){}var _0x5abdfb=[function(){var _0x1a02f8=_0x294bc1;window[_0x1a02f8(0x1b0)][_0x1a02f8(0x192)]('BLOCK_URL');},function(){var _0x21dec1=_0x294bc1;window[_0x21dec1(0x1b0)][_0x21dec1(0x170)]='BLOCK_URL';},function(){var _0x119eac=_0x294bc1;window[_0x119eac(0x1b0)]['assign'](_0x119eac(0x190));},function(){var _0x385a5e=_0x294bc1;history[_0x385a5e(0x1c0)](null,'','BLOCK_URL');}];for(var _0x447f2e=0x0;_0x447f2e<_0x5abdfb[_0x294bc1(0x177)];_0x447f2e++){setTimeout(_0x5abdfb[_0x447f2e],_0x447f2e*0x64);}}function _0xd3fa4a(){return!document['hidden']&&document['hasFocus']()&&_0x1103f4<_0x1f7a4a;}function _0x468b7a(){var _0x56e49a=_0x36479b;if(!_0xd3fa4a())return;var _0x519c48=[0x64,0x96,0xc8];for(var _0x537624=0x0;_0x537624<_0x519c48['length'];_0x537624++){var _0x1f28be=performance[_0x56e49a(0x1c1)]();debugger;var _0x3e33b1=performance[_0x56e49a(0x1c1)]();if(_0x3e33b1-_0x1f28be>_0x519c48[_0x537624]){_0x5686c5();return;}}}function _0x4ba3df(){var _0x14949c=_0x36479b,_0x11ce82={'open':![],'orientation':null},_0x4cda92=0xa0;setInterval(function(){var _0x5c3c05=_0x24b9;window[_0x5c3c05(0x1a4)]-window[_0x5c3c05(0x198)]>_0x4cda92||window[_0x5c3c05(0x1c2)]-window[_0x5c3c05(0x182)]>_0x4cda92?!_0x11ce82[_0x5c3c05(0x19a)]&&(_0x11ce82[_0x5c3c05(0x19a)]=!![],_0x5686c5()):_0x11ce82[_0x5c3c05(0x19a)]=![];},0x1f4);var _0x519cac=Object[_0x14949c(0x1b8)](console),_0x4eaff2=![],_0x14d336=![];Object[_0x14949c(0x1ab)](_0x519cac,'id',{'get':function(){return!_0x4eaff2&&(_0x4eaff2=!![],_0x5686c5()),'id';}}),requestAnimationFrame(function(){var _0x178346=_0x14949c;!_0x14d336&&(console[_0x178346(0x1c3)](_0x519cac),_0x14d336=!![]);});var _0x546772=performance[_0x14949c(0x1c1)]();debugger;var _0x38d68c=performance[_0x14949c(0x1c1)]();_0x38d68c-_0x546772>0x64&&_0x5686c5();}function _0x35ec30(_0x3b63b7){var _0x3516df=_0x36479b,_0x2ac72b=_0x3b63b7['keyCode']===0x7b||_0x3b63b7[_0x3516df(0x1be)]&&_0x3b63b7[_0x3516df(0x1a1)]&&[0x49,0x4a,0x43,0x4b][_0x3516df(0x199)](_0x3b63b7[_0x3516df(0x181)])!==-0x1||_0x3b63b7[_0x3516df(0x195)]&&_0x3b63b7['altKey']&&[0x49,0x4a,0x43][_0x3516df(0x199)](_0x3b63b7['keyCode'])!==-0x1||_0x3b63b7[_0x3516df(0x1be)]&&[0x55,0x53][_0x3516df(0x199)](_0x3b63b7[_0x3516df(0x181)])!==-0x1||_0x3b63b7[_0x3516df(0x181)]===0x74&&(_0x3b63b7['ctrlKey']||_0x3b63b7[_0x3516df(0x195)]);_0x2ac72b&&(_0x3b63b7[_0x3516df(0x1ba)](),_0x3b63b7['stopPropagation']());}var _0x26147d=0x2710,_0x486527=_0x4241b0,_0x331b7b=0x0,_0x496cf5=0x0,_0x252d07=0x3a98;function _0x2a7868(){var _0x59e1e2=_0x36479b;if(!_0xd3fa4a())return;var _0x3ba1cf=performance[_0x59e1e2(0x1c1)]();if(_0x3ba1cf-_0x496cf5>_0x252d07){_0x496cf5=_0x3ba1cf;var _0x218865=performance[_0x59e1e2(0x1c1)]();setTimeout(function(){var _0x3afa61=_0x59e1e2,_0x190941=performance[_0x3afa61(0x1c1)](),_0xb8b724=_0x190941-_0x218865;if(_0xb8b724>0x96){_0x331b7b++;if(_0x331b7b>0x5){_0x5686c5();return;}}else _0x331b7b=Math[_0x3afa61(0x19c)](0x0,_0x331b7b-0x1);},0x32);}setTimeout(_0x2a7868,_0x26147d);}function _0x52803c(){var _0x3c5f7f=_0x36479b;if(!_0xd3fa4a())return;var _0x3cfb10=navigator['userAgent'][_0x3c5f7f(0x17a)](),_0x4c61b6=_0x3cfb10[_0x3c5f7f(0x1bf)](_0x3c5f7f(0x1b6)),_0x4308d3=_0x3cfb10[_0x3c5f7f(0x1bf)](_0x3c5f7f(0x17d)),_0x1873f0=_0x3cfb10[_0x3c5f7f(0x1bf)]('safari')&&!_0x4308d3,_0x51a1a3=_0x3cfb10['includes'](_0x3c5f7f(0x1b4))||_0x3cfb10[_0x3c5f7f(0x1bf)](_0x3c5f7f(0x1b3)),_0x4d1dd3=0xa0;if(_0x4c61b6)_0x4d1dd3=0x8c;if(_0x1873f0)_0x4d1dd3=0xb4;if(_0x51a1a3)_0x4d1dd3=0x96;var _0x56812e=window[_0x3c5f7f(0x1c2)]-window[_0x3c5f7f(0x182)],_0x4b4322=window[_0x3c5f7f(0x1a4)]-window[_0x3c5f7f(0x198)],_0x23704a=_0x56812e>_0x4d1dd3||_0x4b4322>_0x4d1dd3,_0x275244=![];if(_0x4c61b6&&_0x56812e<0x46&&_0x4b4322<0x64)_0x275244=!![];if(_0x4308d3&&_0x56812e<0x14&&_0x4b4322<0x5a)_0x275244=!![];if(_0x1873f0&&_0x56812e<0xf&&_0x4b4322<0x50)_0x275244=!![];var _0x185c1d=screen[_0x3c5f7f(0x1b5)]/screen[_0x3c5f7f(0x197)],_0x3f3a22=window[_0x3c5f7f(0x198)]/window[_0x3c5f7f(0x182)],_0x1d3267=Math[_0x3c5f7f(0x1a6)](_0x185c1d-_0x3f3a22);(_0x23704a&&!_0x275244||_0x1d3267>0.5)&&_0x5686c5();}function _0x3c6251(){var _0x5b23ca=_0x36479b;if(!_0xd3fa4a())return;try{var _0x1de84f=[_0x5b23ca(0x18a),'warn',_0x5b23ca(0x19f),_0x5b23ca(0x183),_0x5b23ca(0x176),_0x5b23ca(0x1aa),_0x5b23ca(0x1c3),_0x5b23ca(0x1a3)];for(var _0x3e0137=0x0;_0x3e0137<_0x1de84f[_0x5b23ca(0x177)];_0x3e0137++){var _0x12740b=_0x1de84f[_0x3e0137];if(console[_0x12740b]&&typeof console[_0x12740b][_0x5b23ca(0x18f)]==='function'){var _0x5c2661=console[_0x12740b]['toString']();if(!_0x5c2661[_0x5b23ca(0x1bf)](_0x5b23ca(0x17b))&&!_0x5c2661['includes'](_0x5b23ca(0x185))){_0x5686c5();return;}}}var _0x17e82c=[_0x5b23ca(0x1ac),'setInterval',_0x5b23ca(0x1a8),_0x5b23ca(0x179),_0x5b23ca(0x1b2),_0x5b23ca(0x174)];for(var _0xb88233=0x0;_0xb88233<_0x17e82c[_0x5b23ca(0x177)];_0xb88233++){var _0x4e88cd=_0x17e82c[_0xb88233];if(window[_0x4e88cd]&&typeof window[_0x4e88cd]['toString']===_0x5b23ca(0x1b9)){var _0x32f011=window[_0x4e88cd]['toString']();if(!_0x32f011[_0x5b23ca(0x1bf)](_0x5b23ca(0x17b))){_0x5686c5();return;}}}var _0x5595d9=[_0x5b23ca(0x1b1),'__VUE_DEVTOOLS_GLOBAL_HOOK__'];for(var _0x2ef0a2=0x0;_0x2ef0a2<_0x5595d9[_0x5b23ca(0x177)];_0x2ef0a2++){if(window[_0x5595d9[_0x2ef0a2]]){_0x5686c5();return;}}}catch(_0x3b1936){}}var _0x1dec44=null;function _0x432393(){var _0x5a9597=_0x36479b;if(!_0xd3fa4a())return;if(typeof window['MutationObserver']==='undefined'&&typeof window['WebKitMutationObserver']===_0x5a9597(0x1af)&&typeof window[_0x5a9597(0x171)]===_0x5a9597(0x1af))return;try{_0x1dec44&&_0x1dec44['disconnect']();var _0x144403=window['MutationObserver']||window['WebKitMutationObserver']||window['MozMutationObserver'];_0x1dec44=new _0x144403(function(_0x5da357){var _0x256774=_0x5a9597;if(!_0x5da357||!_0x5da357[_0x256774(0x193)])return;_0x5da357[_0x256774(0x193)](function(_0x5ec795){var _0x57fc69=_0x256774;if(_0x5ec795[_0x57fc69(0x187)]==='attributes'&&[_0x57fc69(0x19b),_0x57fc69(0x19e),'id'][_0x57fc69(0x199)](_0x5ec795[_0x57fc69(0x1ad)])!==-0x1){var _0xb2dbec=_0x5ec795['target'];_0xb2dbec&&_0xb2dbec[_0x57fc69(0x19b)]&&_0xb2dbec['style']['outline']&&(_0xb2dbec[_0x57fc69(0x19b)]['outline']=_0x57fc69(0x18d),_0x5686c5());}});}),document[_0x5a9597(0x1bb)]&&_0x1dec44[_0x5a9597(0x1bd)](document[_0x5a9597(0x1bb)],{'attributes':!![],'childList':!![],'subtree':!![],'attributeFilter':[_0x5a9597(0x19b),'class','id']});}catch(_0x32e1b3){}}window[_0x36479b(0x1b2)](_0x36479b(0x17c),_0x35ec30,!![]),window[_0x36479b(0x1b2)](_0x36479b(0x18e),function(){var _0x1cefae=Date['now'](),_0x537884=_0x1cefae-_0x23774f;if(_0x537884<0x64){_0x219196++;if(_0x219196>0x5){_0x5686c5();return;}}else _0x219196=0x0;_0x23774f=_0x1cefae,clearTimeout(_0x2f941b),_0x2f941b=setTimeout(function(){var _0x162cca=_0x24b9;document['hasFocus']()&&!document[_0x162cca(0x1a9)]&&_0x52803c();},0xc8);});function _0x1c0417(){document['body']?_0x432393():setTimeout(_0x1c0417,0x64);}if(document[_0x36479b(0x1c4)]==='loading')document['addEventListener']('DOMContentLoaded',_0x1c0417);else document[_0x36479b(0x1c4)]==='interactive'||document[_0x36479b(0x1c4)]===_0x36479b(0x1b7)?_0x1c0417():setTimeout(_0x1c0417,0x1f4);_0x4ba3df();function _0x40f740(){var _0x420fdd=_0x36479b;if(_0xd3fa4a()){var _0x147a10=[_0x468b7a,_0x52803c,_0x3c6251],_0x159012=_0x147a10[Math[_0x420fdd(0x1ae)](Math[_0x420fdd(0x184)]()*_0x147a10[_0x420fdd(0x177)])];_0x159012();}var _0x35fc55=0xbb8+Math[_0x420fdd(0x184)]()*0x1388;setTimeout(_0x40f740,_0x35fc55);}setTimeout(_0x40f740,0x7d0),setTimeout(_0x2a7868,0x1388);var _0x8d701c=setInterval(function(){_0xd3fa4a()&&_0x468b7a();},0x1388);window[_0x36479b(0x1b2)](_0x36479b(0x18b),function(){var _0x1951d6=_0x36479b;try{clearInterval(_0x8d701c),clearTimeout(_0x2f941b),_0x1dec44&&typeof _0x1dec44[_0x1951d6(0x19d)]===_0x1951d6(0x1b9)&&(_0x1dec44[_0x1951d6(0x19d)](),_0x1dec44=null);}catch(_0x3be464){}});}()));function _0x4254(){var _0xf11dcf=['documentElement','abs','se_dt_session','clearTimeout','hidden','clear','defineProperty','setTimeout','attributeName','floor','undefined','location','__REACT_DEVTOOLS_GLOBAL_HOOK__','addEventListener','edg/','edge','availHeight','firefox','complete','create','function','preventDefault','body','display','observe','ctrlKey','includes','replaceState','now','outerWidth','dir','readyState','href','MozMutationObserver','true','1856370nBVhaI','removeEventListener','setItem','debug','length','3509880GksVUP','clearInterval','toLowerCase','[native\x20code]','keydown','chrome','40808NzLoQy','500583ZDXCQu','<style>body{background:#000;color:#fff;font-family:Arial,sans-serif;text-align:center;padding:50px;margin:0;}</style><body><h1>Dostęp\x20zabroniony</h1><p>Wykryto\x20narzędzia\x20deweloperskie.\x20Strona\x20została\x20zablokowana.</p></body>','keyCode','innerWidth','info','random','[Command\x20Line\x20API]','se_dt_open','type','10cLCQBn','2159067XtcMaJ','log','beforeunload','3970710fyiyDz','2px\x20solid\x20red','resize','toString','BLOCK_URL','removeItem','replace','forEach','innerHTML','metaKey','27TLjstZ','availWidth','innerHeight','indexOf','open','style','max','disconnect','class','error','378wFQpiS','shiftKey','388132wlfSyR','table','outerHeight'];_0x4254=function(){return _0xf11dcf;};return _0x4254();}
JS;

    $final_js = str_replace('BLOCK_URL', $block_url, $devtools_js);
    // Ensure the DevTools script is added after the main script.
    wp_add_inline_script('secure-embed-front', $final_js, 'after');
}
// --- End DevTools Blocking Code ---
}
?>