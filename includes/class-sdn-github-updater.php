<?php
/**
 * Built-in GitHub release updater for Steinmetz Dashboard Notes.
 *
 * The repo is public, so no token is needed. Updates are only ever taken
 * from a .zip asset attached to a release of this exact repo; any other
 * download URL is ignored.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDN_GitHub_Updater {

    const OPTION_STATUS = 'sdn_github_updater_status';
    const CACHE_KEY     = 'sdn_github_release';
    const CACHE_TTL     = 6 * HOUR_IN_SECONDS;

    private $repo;
    private $basename;
    private $slug;
    private $version;

    public function __construct($plugin_file, $repo, $version) {
        $this->repo     = $repo;
        $this->basename = plugin_basename($plugin_file);
        $this->slug     = dirname($this->basename);
        $this->version  = $version;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'inject_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_action('upgrader_process_complete', array($this, 'clear_cache'), 10, 0);

        if (is_admin()) {
            add_action('admin_notices', array($this, 'admin_notice'));
            add_action('load-update-core.php', array($this, 'maybe_force_check'));
        }
    }

    /** Release zips for this repo only. */
    private function is_own_package_url($url) {
        return is_string($url) && 0 === strpos($url, 'https://github.com/' . $this->repo . '/releases/download/');
    }

    /* ------------------------------------------------------------------ */
    /* Release lookup                                                     */
    /* ------------------------------------------------------------------ */

    private function get_release($force = false) {
        if (!$force) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        // Read the tag from github.com's "latest release" redirect instead of the
        // REST API: anonymous API calls are capped at 60/hour per IP, and shared
        // hosts (Pressable) exhaust that, so the API answers 403.
        $response = wp_remote_head('https://github.com/' . $this->repo . '/releases/latest', array(
            'headers'     => array('User-Agent' => 'steinmetz-dashboard-notes-updater'),
            'redirection' => 0,
            'timeout'     => 15,
        ));

        $code     = wp_remote_retrieve_response_code($response);
        $location = is_wp_error($response) ? '' : (string) wp_remote_retrieve_header($response, 'location');
        $tag_base = 'https://github.com/' . $this->repo . '/releases/tag/';

        if (is_wp_error($response) || 302 !== $code || 0 !== strpos($location, $tag_base)) {
            $this->set_status(is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . intval($code) . ' (no release found)');
            // Cache the failure briefly so we don't hammer GitHub.
            set_site_transient(self::CACHE_KEY, array(), HOUR_IN_SECONDS);
            return array();
        }

        $tag = rawurldecode(substr($location, strlen($tag_base)));
        if (!preg_match('/^v?\d+(\.\d+){1,3}$/', $tag)) {
            $this->set_status('Unexpected release tag');
            set_site_transient(self::CACHE_KEY, array(), HOUR_IN_SECONDS);
            return array();
        }

        $package = 'https://github.com/' . $this->repo . '/releases/download/' . $tag . '/' . $this->slug . '.zip';

        $release = array(
            'version'   => ltrim($tag, 'vV'),
            'package'   => $this->is_own_package_url($package) ? esc_url_raw($package) : '',
            'html_url'  => esc_url_raw($tag_base . $tag),
            'notes'     => 'See the release notes on GitHub: ' . $tag_base . $tag,
            'published' => '',
        );

        $this->set_status('ok');
        set_site_transient(self::CACHE_KEY, $release, self::CACHE_TTL);
        return $release;
    }

    private function set_status($status) {
        update_option(self::OPTION_STATUS, array(
            'status'  => sanitize_text_field($status),
            'checked' => time(),
        ), false);
    }

    public function clear_cache() {
        delete_site_transient(self::CACHE_KEY);
    }

    public function maybe_force_check() {
        if (!empty($_GET['force-check']) && current_user_can('update_plugins')) {
            $this->clear_cache();
        }
    }

    /* ------------------------------------------------------------------ */
    /* WordPress update hooks                                             */
    /* ------------------------------------------------------------------ */

    public function inject_update($transient) {
        if (!is_object($transient)) {
            return $transient;
        }
        $release = $this->get_release();
        if (empty($release['version']) || empty($release['package'])) {
            return $transient;
        }

        $item = (object) array(
            'id'          => $this->basename,
            'slug'        => $this->slug,
            'plugin'      => $this->basename,
            'new_version' => $release['version'],
            'url'         => $release['html_url'],
            'package'     => $release['package'],
        );

        if (version_compare($release['version'], $this->version, '>')) {
            $transient->response[$this->basename] = $item;
            unset($transient->no_update[$this->basename]);
        } else {
            $transient->no_update[$this->basename] = $item;
            unset($transient->response[$this->basename]);
        }
        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }
        $release = $this->get_release();
        if (empty($release['version'])) {
            return $result;
        }
        return (object) array(
            'name'          => 'Steinmetz Dashboard Notes',
            'slug'          => $this->slug,
            'version'       => $release['version'],
            'author'        => 'Steinmetz Ventures LLC',
            'homepage'      => $release['html_url'],
            'last_updated'  => $release['published'],
            'sections'      => array(
                'changelog' => wpautop(esc_html($release['notes'])),
            ),
            'download_link' => $release['package'],
        );
    }

    public function admin_notice() {
        if (!current_user_can('update_plugins')) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, array('plugins', 'update-core'), true)) {
            return;
        }
        $status = get_option(self::OPTION_STATUS, array());
        if (!empty($status['status']) && 'ok' !== $status['status']) {
            printf(
                '<div class="notice notice-warning"><p><strong>Steinmetz Dashboard Notes:</strong> update check failed (%s).</p></div>',
                esc_html($status['status'])
            );
        }
    }
}
