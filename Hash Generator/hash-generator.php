<?php
/*
Plugin Name: Hash Generator
Description: A plugin to generate a data hash for a given URL.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Shortcode to display the hash generator form
function hash_generator_shortcode() {
    ob_start();
    ?>
    <div id="hash-generator">
        <input type="text" id="url-input" placeholder="Enter URL" />
        <button id="generate-hash">Generate Hash</button>
        <p id="hash-output"></p>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('hash_generator', 'hash_generator_shortcode');

// Enqueue the JavaScript file
function hash_generator_enqueue_scripts() {
    wp_enqueue_script('hash-generator-script', plugin_dir_url(__FILE__) . 'hash-generator.js', array('jquery'), null, true);
    wp_localize_script('hash-generator-script', 'hash_generator_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'hash_generator_enqueue_scripts');

// AJAX handler to generate the hash
function generate_hash() {
    if (isset($_POST['url'])) {
        $url = sanitize_text_field($_POST['url']);
        $hash = hash('sha256', $url);
        wp_send_json_success($hash);
    }
    wp_send_json_error('No URL provided.');
}
add_action('wp_ajax_generate_hash', 'generate_hash');
add_action('wp_ajax_nopriv_generate_hash', 'generate_hash');
?>