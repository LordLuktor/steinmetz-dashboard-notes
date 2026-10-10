<?php
/**
 * Plugin Name: Steinmetz Dashboard Notes
 * Description: Adds a simple notes section to the WordPress dashboard for administrators.
 * Version: 1.3.0
 * Author: Steinmetz Ventures LLC
 * Author URI: https://apexweb.us
 * Update URI: https://github.com/LordLuktor/steinmetz-dashboard-notes
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL2+
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SDN_VERSION', '1.3.0');
define('SDN_GITHUB_REPO', 'LordLuktor/steinmetz-dashboard-notes');

// Built-in updater: checks this plugin's own public GitHub releases (no Git Updater needed).
require_once plugin_dir_path(__FILE__) . 'includes/class-sdn-github-updater.php';
new SDN_GitHub_Updater(__FILE__, SDN_GITHUB_REPO, SDN_VERSION);

// Add the dashboard widget
add_action('wp_dashboard_setup', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    wp_add_dashboard_widget(
        'dashboard_notes_widget',
        'Dashboard Notes',
        'render_dashboard_notes_widget'
    );
});

// Render the dashboard widget
function render_dashboard_notes_widget() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $notes = get_option('dashboard_notes_text', '');
    ?>
    <form method="post">
        <textarea name="dashboard_notes_text" rows="10" style="width:100%;"><?php echo esc_textarea($notes); ?></textarea>
        <?php wp_nonce_field('save_dashboard_notes', 'dashboard_notes_nonce'); ?>
        <p><input type="submit" value="Save Notes" class="button button-primary"></p>
    </form>
    <?php
}

// Save the notes
add_action('admin_init', function () {
    if (
        isset($_POST['dashboard_notes_text']) &&
        current_user_can('manage_options') &&
        check_admin_referer('save_dashboard_notes', 'dashboard_notes_nonce')
    ) {
        update_option('dashboard_notes_text', sanitize_textarea_field(wp_unslash($_POST['dashboard_notes_text'])));
    }
});
