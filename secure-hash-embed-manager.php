<?php
/*
Plugin Name: Secure Hash & Embed Manager
Description: Manages secure links with hashed IDs, hides real URLs, includes DevTools blocking plus sorting/searching.
Version: 0.9
Author: Sainaif | https://github.com/Sainaif
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        'Secure Embed Manager',         // Page title
        'Secure Embed Manager',         // Menu title
        'manage_options',               // Capability required
        'secure-embed',                 // Menu slug
        'secure_embed_admin_page',      // Function to display the page
        'dashicons-admin-links',        // Icon
        20                              // Position
    );

    add_submenu_page(
        'secure-embed',                 // Parent slug
        'Database Management',          // Page title
        'Database Management',          // Menu title
        'manage_options',               // Capability
        'secure-embed-db',              // Menu slug
        'secure_embed_db_page'          // Function to display the page
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
    $showEditDelete = get_option( 'secure_embed_show_edit_delete', 'on' );

    // Process form submission to add a new video record
    if ( isset( $_POST['add_link'] ) ) {
        $db_name    = sanitize_text_field( $_POST['db_name'] );
        $embed_name = sanitize_text_field( $_POST['embed_name'] );
        $link       = esc_url_raw( $_POST['link'] );

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE link=%s", $link ) );
        if ( $exists ) {
            echo '<div class="notice notice-error"><p>This link already exists in the database.</p></div>';
        } elseif ( $db_name && $embed_name && $link ) {
            $unique_id = secure_embed_generate_unique_id( $wpdb, $table_name );
            $wpdb->insert( $table_name, [
                'db_name'    => $db_name,
                'embed_name' => $embed_name,
                'link'       => $link,
                'unique_id'  => $unique_id
            ] );
            echo '<div class="notice notice-success"><p>Video added successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Please fill out all fields.</p></div>';
        }
    }

    // Process deletion of a video record and reindex IDs
    if ( isset( $_POST['delete_link'] ) ) {
        $del_id = intval( $_POST['id'] );
        $wpdb->delete( $table_name, ['id' => $del_id], ['%d'] );
        $wpdb->query( "SET @count=0;" );
        $wpdb->query( "UPDATE $table_name SET id=(@count:=@count+1) ORDER BY id ASC;" );
        $wpdb->query( "ALTER TABLE $table_name AUTO_INCREMENT=1;" );
        echo '<div class="notice notice-success"><p>Video deleted and IDs reindexed.</p></div>';
    }

    // Process updating an existing record
    if ( isset( $_POST['update_record'] ) ) {
        $edit_id   = intval( $_POST['edit_id'] );
        $new_db    = sanitize_text_field( $_POST['edit_db_name'] );
        $new_embed = sanitize_text_field( $_POST['edit_embed_name'] );
        $new_link  = esc_url_raw( $_POST['edit_link'] );

        $wpdb->update( $table_name, [
            'db_name'    => $new_db,
            'embed_name' => $new_embed,
            'link'       => $new_link
        ], ['id' => $edit_id], ['%s', '%s', '%s'], ['%d'] );
        echo '<div class="notice notice-success"><p>Record updated for ID ' . $edit_id . '.</p></div>';
    }

    // Retrieve a record for editing if the edit_id query parameter is set
    $edit_id = isset( $_GET['edit_id'] ) ? intval( $_GET['edit_id'] ) : 0;
    $edit_row = $edit_id > 0 ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id=%d", $edit_id ), ARRAY_A ) : null;

    // Handle search and sort parameters
    $searchTerm = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
    $order = ( isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'asc' ) ? 'asc' : 'desc';

    if ( $searchTerm !== '' ) {
        $like = '%' . $searchTerm . '%';
        $videos = $wpdb->get_results( $wpdb->prepare( "
            SELECT * FROM $table_name
            WHERE (db_name LIKE %s OR embed_name LIKE %s OR link LIKE %s)
            ORDER BY id $order
        ", $like, $like, $like ), ARRAY_A );
    } else {
        $videos = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id $order", ARRAY_A );
    }

    // Prepare variables for toggling sort order
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

        <!-- Searching Form -->
        <form method="GET" style="margin-bottom:10px;">
            <input type="hidden" name="page" value="secure-embed">
            <input type="text" name="s" value="<?php echo esc_attr( $searchTerm ); ?>" placeholder="Search partial match..." style="width:200px;">
            <input type="submit" class="button button-secondary" value="Search">
        </form>

        <!-- Sorting Controls -->
        <p>
            <strong>Sort by ID:</strong> <?php echo esc_html( $sortLabel ); ?>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url( $toggleUrl ); ?>" class="button button-secondary"><?php echo esc_html( $toggleLabel ); ?></a>
        </p>

        <!-- Add New Video Form -->
        <h2>Add a New Video</h2>
        <form method="POST" style="margin-bottom:20px;">
            <table class="form-table">
                <tr>
                    <th><label for="db_name">Admin-Only Name</label></th>
                    <td><input type="text" name="db_name" style="<?php echo esc_attr( $fieldStyle ); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="embed_name">Embed Name (public)</label></th>
                    <td><input type="text" name="embed_name" style="<?php echo esc_attr( $fieldStyle ); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="link">Real Video URL</label></th>
                    <td><input type="url" name="link" style="<?php echo esc_attr( $fieldStyle ); ?>" required></td>
                </tr>
            </table>
            <?php submit_button( 'Add Video', 'primary', 'add_link' ); ?>
        </form>

        <!-- Edit Record Form (if applicable) -->
        <?php if ( $edit_row ) : ?>
            <hr>
            <h2>Edit Record (ID <?php echo $edit_row['id']; ?>)</h2>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?php echo $edit_row['id']; ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="edit_db_name">Admin-Only Name</label></th>
                        <td><input type="text" name="edit_db_name" value="<?php echo esc_attr( $edit_row['db_name'] ); ?>" style="<?php echo esc_attr( $fieldStyle ); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="edit_embed_name">Embed Name (public)</label></th>
                        <td><input type="text" name="edit_embed_name" value="<?php echo esc_attr( $edit_row['embed_name'] ); ?>" style="<?php echo esc_attr( $fieldStyle ); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="edit_link">Real Video URL</label></th>
                        <td><input type="url" name="edit_link" value="<?php echo esc_attr( $edit_row['link'] ); ?>" style="<?php echo esc_attr( $fieldStyle ); ?>" required></td>
                    </tr>
                </table>
                <?php submit_button( 'Save Changes', 'primary', 'update_record' ); ?>
                <a class="button button-secondary" href="<?php echo esc_url( remove_query_arg( 'edit_id' ) ); ?>">Cancel</a>
            </form>
            <hr>
        <?php endif; ?>

        <!-- Existing Videos Table -->
        <h2>Existing Videos</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>db_name</th>
                    <th>Embed Name</th>
                    <th>Real Link</th>
                    <th>Unique ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $videos ) ) : ?>
                <?php foreach ( $videos as $v ) : ?>
                <tr>
                    <td><?php echo $v['id']; ?></td>
                    <td><?php echo esc_html( $v['db_name'] ); ?></td>
                    <td><?php echo esc_html( $v['embed_name'] ); ?></td>
                    <td><?php echo esc_url( $v['link'] ); ?></td>
                    <td><?php echo esc_html( $v['unique_id'] ); ?></td>
                    <td>
                    <?php if ( get_option( 'secure_embed_show_edit_delete', 'on' ) === 'on' ) : ?>
                        <!-- Delete Action (with inline confirmation) -->
                        <div class="delete-section" style="display:inline-block;">
                            <form method="POST" style="display:inline-block; margin:0; padding:0;">
                                <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                                <button type="button" class="button button-small delete-init">Delete</button>
                                <input type="submit" name="delete_link" value="Confirm Delete" class="button button-small button-danger delete-confirm" style="display:none; margin-left:5px;">
                                <button type="button" class="button button-secondary delete-cancel" style="display:none;">Cancel</button>
                            </form>
                        </div>

                        <!-- Edit Action (with inline confirmation) -->
                        <?php
                            $editLink = add_query_arg( [
                                'page'    => 'secure-embed',
                                'edit_id' => $v['id'],
                                's'       => $searchTerm,
                                'order'   => $order
                            ], admin_url( 'admin.php' ) );
                        ?>
                        <div class="edit-section" style="display:inline-block; margin-left:10px;">
                            <a href="#" class="button button-secondary edit-init">Edit</a>
                            <span class="edit-confirm-wrap" style="display:none;">
                                <button type="button" class="button button-small button-primary edit-yes" data-editlink="<?php echo esc_url( $editLink ); ?>">Confirm Edit</button>
                                <button type="button" class="button button-secondary edit-no">Cancel</button>
                            </span>
                        </div>
                    <?php endif; ?>
                        <!-- Copy Embed Code Action -->
                        <button class="button button-primary" onclick="copyEmbedCode('<?php echo esc_js( $v['embed_name'] ); ?>','<?php echo esc_js( $v['unique_id'] ); ?>', this)">Copy Embed Code</button>
                        <span class="copy-notice" style="color:green; margin-left:5px; visibility:hidden;"></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="6">No videos found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
    // Inline JavaScript for copying embed code and inline confirmation actions.
    // This script is output without any additional comments.
    function copyEmbedCode(embedName, uniqueId, btnRef) {
        const snippet = `<div class="video-container">
    <a href="#" class="video-toggler" data-id="${uniqueId}">${embedName}</a>
    <div class="video-content" style="display:none;">
        <iframe src="about:blank" frameborder="0" width="640" height="360" allowfullscreen></iframe>
    </div>
</div>`;
        navigator.clipboard.writeText(snippet).then(() => {
            const noticeSpan = btnRef.parentElement.querySelector('.copy-notice');
            noticeSpan.textContent = 'Copied!';
            noticeSpan.style.visibility = 'visible';
            setTimeout(() => { noticeSpan.style.visibility = 'hidden'; }, 2000);
        }).catch(e => { console.error('Failed to copy embed code:', e); });
    }

    document.addEventListener('DOMContentLoaded', function(){
        // DELETE confirmation handlers
        document.querySelectorAll('.delete-init').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                const form = btn.closest('form');
                btn.style.display = 'none';
                form.querySelector('.delete-confirm').style.display = 'inline-block';
                form.querySelector('.delete-cancel').style.display = 'inline-block';
            });
        });
        document.querySelectorAll('.delete-cancel').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                const form = btn.closest('form');
                form.querySelector('.delete-init').style.display = 'inline-block';
                form.querySelector('.delete-confirm').style.display = 'none';
                btn.style.display = 'none';
            });
        });

        // EDIT confirmation handlers
        document.querySelectorAll('.edit-init').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                btn.style.display = 'none';
                btn.parentElement.querySelector('.edit-confirm-wrap').style.display = 'inline-block';
            });
        });
        document.querySelectorAll('.edit-no').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                const spanWrap = btn.closest('.edit-confirm-wrap');
                spanWrap.style.display = 'none';
                spanWrap.parentElement.querySelector('.edit-init').style.display = 'inline-block';
            });
        });
        document.querySelectorAll('.edit-yes').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                window.location = btn.getAttribute('data-editlink');
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
 * @param WPDB $wpdb      The global WPDB object.
 * @param string $table_name The name of the table to check.
 * @return string         The unique identifier.
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

    // Download database as JSON
    if ( isset( $_POST['download_db'] ) ) {
        $records = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );
        $jsonStr = json_encode( $records, JSON_PRETTY_PRINT );
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="secure-embed-backup.json"' );
        header( 'Content-Transfer-Encoding: binary' );
        header( 'Content-Length: ' . strlen( $jsonStr ) );
        echo $jsonStr;
        exit;
    }

    // Restore database from JSON upload
    if ( isset( $_POST['upload_db'] ) && $_POST['confirm_restore'] === 'I know this is dangerous and I will lose my current database' ) {
        if ( ! empty( $_FILES['db_file']['tmp_name'] ) ) {
            $data = file_get_contents( $_FILES['db_file']['tmp_name'] );
            $arr = json_decode( $data, true );
            if ( is_array( $arr ) ) {
                $wpdb->query( "TRUNCATE TABLE $table_name" );
                foreach ( $arr as $row ) {
                    if ( ! empty( $row['link'] ) ) {
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
                    }
                }
                echo '<div class="notice notice-success"><p>Database restored successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Invalid JSON file format.</p></div>';
            }
        }
    }

    // Reset database
    if ( isset( $_POST['reset_db'] ) && $_POST['confirm_reset'] === 'I know this is dangerous and I will lose my current database' ) {
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
        secure_embed_create_table();
        echo '<div class="notice notice-success"><p>Database has been reset.</p></div>';
    }

    // CSV Import
    if ( isset( $_POST['import_csv'] ) ) {
        if ( ! empty( $_FILES['csv_file']['tmp_name'] ) ) {
            $handle = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
            if ( $handle ) {
                $count = 0;
                while ( ( $line = fgetcsv( $handle ) ) !== false ) {
                    if ( count( $line ) >= 3 ) {
                        $db_name = sanitize_text_field( $line[0] );
                        $embed_n = sanitize_text_field( $line[1] );
                        $link    = esc_url_raw( $line[2] );
                        $exists  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE link=%s", $link ) );
                        if ( ! $exists && ! empty( $link ) ) {
                            $uniq = secure_embed_generate_unique_id( $wpdb, $table_name );
                            $wpdb->insert( $table_name, [
                                'db_name'    => $db_name,
                                'embed_name' => $embed_n,
                                'link'       => $link,
                                'unique_id'  => $uniq
                            ] );
                            $count++;
                        }
                    }
                }
                fclose( $handle );
                echo '<div class="notice notice-success"><p>Imported ' . $count . ' video(s).</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Could not open CSV file.</p></div>';
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Database Management</h1>
        <form method="POST">
            <?php submit_button( 'Download Database as JSON', 'secondary', 'download_db' ); ?>
        </form>
        <hr>
        <h3>Restore Database (Upload JSON)</h3>
        <p><strong>Warning:</strong> Overwrites existing records. Type <code>I know this is dangerous and I will lose my current database</code>.</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="db_file" accept=".json" required>
            <br><br>
            <input type="text" name="confirm_restore" placeholder="I know this is dangerous and I will lose my current database" required>
            <?php submit_button( 'Restore Database', 'secondary', 'upload_db' ); ?>
        </form>
        <hr>
        <h3>Reset Database</h3>
        <p><strong>Warning:</strong> Deletes all records. Type <code>I know this is dangerous and I will lose my current database</code>.</p>
        <form method="POST">
            <input type="text" name="confirm_reset" placeholder="I know this is dangerous and I will lose my current database" required>
            <?php submit_button( 'Reset Database', 'delete', 'reset_db' ); ?>
        </form>
        <hr>
        <h3>Bulk Import (CSV)</h3>
        <p>Format: <code>DB Name, Embed Name, Video URL</code></p>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv,text/csv" required>
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

    if ( isset( $_POST['secure_embed_block_devtools'] ) ) {
        $val = ( $_POST['secure_embed_block_devtools'] === 'on' ) ? 'on' : 'off';
        update_option( 'secure_embed_block_devtools', $val );
        echo '<div class="notice notice-success"><p>DevTools blocking: <strong>' . $val . '</strong>.</p></div>';
    }

    if ( isset( $_POST['secure_embed_block_devtools_url'] ) ) {
        $u = sanitize_text_field( $_POST['secure_embed_block_devtools_url'] );
        update_option( 'secure_embed_block_devtools_url', $u );
        echo '<div class="notice notice-success"><p>DevTools 404 redirect URL: <strong>' . $u . '</strong>.</p></div>';
    }

    if ( isset( $_POST['secure_embed_input_width'] ) ) {
        $w = absint( $_POST['secure_embed_input_width'] );
        if ( $w < 50 ) { $w = 50; }
        if ( $w > 2000 ) { $w = 2000; }
        update_option( 'secure_embed_input_width', $w );
        echo '<div class="notice notice-success"><p>Field width set to <strong>' . $w . 'px</strong>.</p></div>';
    }

    if ( isset( $_POST['secure_embed_show_edit_delete'] ) ) {
        $editdel = ( $_POST['secure_embed_show_edit_delete'] === 'on' ) ? 'on' : 'off';
        update_option( 'secure_embed_show_edit_delete', $editdel );
        echo '<div class="notice notice-success"><p>Edit/Delete Buttons: <strong>' . $editdel . '</strong>.</p></div>';
    }

    $toggle   = get_option( 'secure_embed_block_devtools', 'off' );
    $redirect = get_option( 'secure_embed_block_devtools_url', site_url( '/404' ) );
    $wVal     = get_option( 'secure_embed_input_width', 400 );
    $ed       = get_option( 'secure_embed_show_edit_delete', 'on' );
    ?>
    <div class="wrap">
        <h1>Plugin Settings</h1>
        <form method="POST">
            <h3>DevTools Blocking</h3>
            <label>
                <input type="radio" name="secure_embed_block_devtools" value="off" <?php checked( $toggle, 'off' ); ?>>
                Off
            </label>
            <br>
            <label>
                <input type="radio" name="secure_embed_block_devtools" value="on" <?php checked( $toggle, 'on' ); ?>>
                On
            </label>
            <hr>
            <h3>DevTools 404 Redirect URL</h3>
            <input type="text" name="secure_embed_block_devtools_url" value="<?php echo esc_attr( $redirect ); ?>" style="width:300px;">
            <br><br>
            <hr>
            <h3>Field Width (pixels)</h3>
            <p>Default 400, range 50..2000.</p>
            <input type="number" name="secure_embed_input_width" min="50" max="2000" step="10" value="<?php echo esc_attr( $wVal ); ?>">
            <hr>
            <h3>Show Edit/Delete Buttons</h3>
            <label>
                <input type="radio" name="secure_embed_show_edit_delete" value="off" <?php checked( $ed, 'off' ); ?>>
                Off (Hide Edit & Delete)
            </label>
            <br>
            <label>
                <input type="radio" name="secure_embed_show_edit_delete" value="on" <?php checked( $ed, 'on' ); ?>>
                On (Show Edit & Delete)
            </label>
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
    $unique_id = isset( $_REQUEST['unique_id'] ) ? sanitize_text_field( $_REQUEST['unique_id'] ) : '';
    if ( ! $unique_id ) {
        wp_send_json_error( [ 'message' => 'No unique ID provided' ] );
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'secure_embed_videos';
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT link FROM $table_name WHERE unique_id=%s", $unique_id ), ARRAY_A );
    if ( $row && ! empty( $row['link'] ) ) {
        wp_send_json_success( [ 'url' => $row['link'] ] );
    } else {
        wp_send_json_error( [ 'message' => 'Video not found' ] );
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
 * JavaScript for detecting open DevTools. The original non-obfuscated code is commented
 * out for reference and is not output to end users.
 */
add_action( 'wp_enqueue_scripts', 'secure_embed_enqueue_frontend_script' );
function secure_embed_enqueue_frontend_script() {
    $ajax_url = admin_url( 'admin-ajax.php' );

    // Main script for toggling the video iframe
    $main_script = "
    document.addEventListener('DOMContentLoaded', function(){
        var toggles = document.querySelectorAll('.video-toggler');
        toggles.forEach(function(toggler){
            toggler.addEventListener('click', function(e){
                e.preventDefault();
                var uniqueID = toggler.getAttribute('data-id');
                var container = toggler.closest('.video-container');
                var iframe = container.querySelector('iframe');
                var content = container.querySelector('.video-content');
                if(content.style.display === 'none'){
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '$ajax_url', true);
                    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                    xhr.onload = function(){
                        if(xhr.status === 200){
                            try{
                                var res = JSON.parse(xhr.responseText);
                                if(res.success && res.data.url){
                                    iframe.src = res.data.url;
                                    content.style.display = 'block';
                                }
                            } catch(e){ console.error(e); }
                        }
                    };
                    xhr.send('action=get_video_url&unique_id=' + encodeURIComponent(uniqueID));
                } else {
                    content.style.display = 'none';
                    iframe.src = 'about:blank';
                }
            });
        });
    });
    ";

    wp_register_script( 'secure-embed-front', '', [], null, true );
    wp_enqueue_script( 'secure-embed-front' );
    wp_add_inline_script( 'secure-embed-front', $main_script );

// --- Begin DevTools Blocking Code ---
// The following non-obfuscated reference code is preserved as a comment for internal reference.
/*
(function(){
    var lastTimestamp = performance.now();
    document.addEventListener('visibilitychange', function(){
        if (!document.hidden) {
            lastTimestamp = performance.now();
            console.log("Page is visible; frame timer reset.");
        }
    });
    
    if (localStorage.getItem('devtools_open') === 'true') {
        console.log("Persistent flag: DevTools open, redirecting.");
        document.body.innerHTML = "";
        window.location.href = 'BLOCK_URL';
    }
    
    var redirected = false;
    
    function redirectToBlock() {
        if (redirected) return;
        redirected = true;
        localStorage.setItem('devtools_open', 'true');
        console.log("DevTools detected, redirecting now.");
        document.body.innerHTML = "";
        window.location.href = 'BLOCK_URL';
    }
    
    function removeDevtoolsFlag() {
        localStorage.removeItem('devtools_open');
    }
    
    function safeRedirect() {
        if (document.hidden || !document.hasFocus()){
            console.log("Page not active; not redirecting.");
            return;
        }
        redirectToBlock();
    }
    
    function checkDebugger() {
        var start = performance.now();
        debugger;
        var end = performance.now();
        if (end - start > 100) {
            safeRedirect();
        } else {
            removeDevtoolsFlag();
        }
    }
    
    function keyHandler(e) {
        if (e.keyCode === 123 ||
           (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74))) {
            safeRedirect();
        }
    }
    window.addEventListener('keydown', keyHandler);
    
    function checkFrame() {
        var now = performance.now();
        if (now - lastTimestamp > 2000) {
            safeRedirect();
        }
        lastTimestamp = now;
        requestAnimationFrame(checkFrame);
    }
    requestAnimationFrame(checkFrame);
    
    setInterval(function(){
        checkDebugger();
    }, 500);
})();
*/
// --- End Updated Non-Obfuscated Code (hidden from end users) ---

// Improved DevTools Blocking Code (obfuscated)
$toggle = get_option('secure_embed_block_devtools', 'off');
if ($toggle === 'on') {
    $block_url = esc_url_raw(get_option('secure_embed_block_devtools_url', site_url('/404')));
    
    $obf_js = <<<'JS'
(function(){
    var _0x1a2b3c = performance.now();
    document.addEventListener('visibilitychange', function(){
        if (!document.hidden) {
            _0x1a2b3c = performance.now();
            console.log("Page is visible; frame timer reset.");
        }
    });
    if (localStorage.getItem('devtools_open') === 'true') {
        console.log("Persistent flag: DevTools open, redirecting.");
        document.body.innerHTML = "";
        window.location.href = 'BLOCK_URL';
    }
    var _0x9f8e7d = false;
    function _0x54321() {
        if (_0x9f8e7d) return;
        _0x9f8e7d = true;
        localStorage.setItem('devtools_open', 'true');
        console.log("DevTools detected, redirecting now.");
        document.body.innerHTML = "";
        window.location.href = 'BLOCK_URL';
    }
    function _0xabcdef() {
        localStorage.removeItem('devtools_open');
    }
    function _0x13579() {
        if (document.hidden || !document.hasFocus()){
            console.log("Page not active; not redirecting.");
            return;
        }
        _0x54321();
    }
    function _0x24680() {
        var _0x111213 = performance.now();
        debugger;
        var _0x141516 = performance.now();
        if (_0x141516 - _0x111213 > 100) {
            _0x13579();
        } else {
            _0xabcdef();
        }
    }
    function _0x192837(e) {
        if (e.keyCode === 123 ||
            (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74))) {
            _0x13579();
        }
    }
    window.addEventListener('keydown', _0x192837);
    function _0x564738() {
        var _0x171819 = performance.now();
        if (_0x171819 - _0x1a2b3c > 2000) {
            _0x13579();
        }
        _0x1a2b3c = _0x171819;
        requestAnimationFrame(_0x564738);
    }
    requestAnimationFrame(_0x564738);
    setInterval(function(){
        _0x24680();
    }, 500);
})();
JS;
    
    $final_js = str_replace('BLOCK_URL', $block_url, $obf_js);
    wp_add_inline_script('secure-embed-front', $final_js);
}
// --- End DevTools Blocking Code ---
}
?>