<?php
/*
Plugin Name: Hidden Iframe Link
Description: A plugin to hide iframe URLs using tokens and AJAX.
Version: 1.1
Author: Sainaif from HolyThighbleSubs
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function hide_iframe_urls($content) {
    if (is_single() || is_page()) {
        // Regular expression pattern to match all iframe URLs, case insensitive
        $pattern = '/<div class="video-container">\s*<a href="[^"]+" class="video-toggler">([^<]+)<\/a>\s*<div class="video-content" style="display:none;">\s*<iframe\s+src="([^"]+)"[^>]*><\/iframe>\s*<\/div>\s*<\/div>/i';
        preg_match_all($pattern, $content, $matches);

        if (!empty($matches[2])) {
            foreach ($matches[2] as $index => $url) {
                $hash = hash('sha256', $url);
                set_transient('iframe_url_' . $hash, $url, 3600);

                $name = $matches[1][$index];
                $iframe_placeholder = '<div class="video-container">
                    <a href="#" class="video-toggler" data-hash="' . $hash . '">' . $name . '</a>
                    <div class="video-content" style="display:none;">
                        <iframe data-hash="' . $hash . '" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" width="640" height="360" allowfullscreen></iframe>
                    </div>
                </div>';

                $content = str_replace($matches[0][$index], $iframe_placeholder, $content);
            }
        }
    }
    return $content;
}
add_filter('the_content', 'hide_iframe_urls');

function get_iframe_url() {
    if (isset($_POST['hash'])) {
        $hash = sanitize_text_field($_POST['hash']);
        $url = get_transient('iframe_url_' . $hash);
        if ($url) {
            wp_send_json_success($url);
        } else {
            wp_send_json_error('Invalid hash or expired.');
        }
    }
    wp_send_json_error('No hash provided.');
}
add_action('wp_ajax_get_iframe_url', 'get_iframe_url');
add_action('wp_ajax_nopriv_get_iframe_url', 'get_iframe_url');

function hidden_iframe_enqueue_scripts() {
    wp_enqueue_script('hidden-iframe-script', plugin_dir_url(__FILE__) . 'hidden-iframe.js', array('jquery'), null, true);
    wp_localize_script('hidden-iframe-script', 'hidden_iframe_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'hidden_iframe_enqueue_scripts');
?>