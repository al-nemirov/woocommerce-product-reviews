<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GitHub-based plugin updater.
 * Checks GitHub releases API for new versions and integrates with WordPress update system.
 */
class NR_GitHub_Updater {

    private $slug;
    private $plugin_file;
    private $github_repo;
    private $github_url;
    private $cache_key;
    private $cache_ttl = 21600; // 6 hours
    private $force_check = false;

    public function __construct($plugin_file, $github_repo) {
        $this->plugin_file = $plugin_file;
        $this->slug        = plugin_basename($plugin_file);
        $this->github_repo = $github_repo;
        $this->github_url  = 'https://api.github.com/repos/' . $github_repo;
        $this->cache_key   = 'nr_gh_update_' . md5($github_repo);

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);

        // Force-check: "Check again" button on Updates page or custom param
        if (is_admin() && (isset($_GET['force-check']) || isset($_GET['nr_force_check']))) {
            $this->force_check = true;
            $this->flush_cache();
        }
    }

    /**
     * Aggressively clear all layers of cache (transient + object cache + site transient).
     */
    private function flush_cache() {
        // 1. Standard transient delete
        delete_transient($this->cache_key);

        // 2. Direct object cache delete (for Redis/Memcached)
        wp_cache_delete($this->cache_key, 'transient');
        wp_cache_delete('timeout_' . $this->cache_key, 'transient');

        // 3. Also try site transients (multisite compat)
        delete_site_transient($this->cache_key);
        wp_cache_delete($this->cache_key, 'site-transient');
        wp_cache_delete('timeout_' . $this->cache_key, 'site-transient');

        // 4. Direct DB fallback
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
            '_transient_' . $this->cache_key,
            '_transient_timeout_' . $this->cache_key
        ));
    }

    /**
     * Check GitHub for a newer release and inject into WP update transient.
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->get_remote_info();
        if (!$remote || empty($remote['version'])) {
            return $transient;
        }

        $current_version = $transient->checked[$this->slug] ?? NR_VERSION;

        if (version_compare($remote['version'], $current_version, '>')) {
            $transient->response[$this->slug] = (object) [
                'slug'        => dirname($this->slug),
                'plugin'      => $this->slug,
                'new_version' => $remote['version'],
                'url'         => 'https://github.com/' . $this->github_repo,
                'package'     => $remote['zipball_url'],
                'icons'       => [],
                'banners'     => [],
            ];
        }

        return $transient;
    }

    /**
     * Provide plugin info for the "View details" popup.
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== dirname($this->slug)) {
            return $result;
        }

        $remote = $this->get_remote_info();
        if (!$remote) {
            return $result;
        }

        $plugin_data = get_plugin_data($this->plugin_file);

        return (object) [
            'name'          => $plugin_data['Name'],
            'slug'          => dirname($this->slug),
            'version'       => $remote['version'],
            'author'        => $plugin_data['Author'],
            'homepage'      => 'https://github.com/' . $this->github_repo,
            'download_link' => $remote['zipball_url'],
            'sections'      => [
                'description' => $plugin_data['Description'],
                'changelog'   => $remote['body'] ?: __('See GitHub releases for details.', 'woocommerce-product-reviews'),
            ],
            'requires'      => $plugin_data['RequiresWP'] ?? '5.0',
            'requires_php'  => $plugin_data['RequiresPHP'] ?? '7.2',
            'last_updated'  => $remote['published_at'] ?? '',
        ];
    }

    /**
     * After install, rename the extracted folder to match plugin slug.
     */
    public function post_install($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->slug) {
            return $result;
        }

        global $wp_filesystem;
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($this->slug);
        $wp_filesystem->move($result['destination'], $plugin_dir);
        $result['destination'] = $plugin_dir;

        activate_plugin($this->slug);

        return $result;
    }

    /**
     * Fetch latest release info from GitHub (cached).
     * On force-check: skip cache entirely, always fetch fresh.
     */
    private function get_remote_info() {
        // Skip cache on force-check
        if (!$this->force_check) {
            $cached = get_transient($this->cache_key);
            // Only use cache if it's a valid array WITH version key
            if (is_array($cached) && !empty($cached['version'])) {
                return $cached;
            }
        }

        $response = wp_remote_get($this->github_url . '/releases/latest', [
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version'),
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Don't cache failures — let next check try again
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['tag_name'])) {
            return null;
        }

        $info = [
            'version'      => ltrim($data['tag_name'], 'vV'),
            'zipball_url'  => $data['zipball_url'] ?? '',
            'body'         => $data['body'] ?? '',
            'published_at' => $data['published_at'] ?? '',
        ];

        set_transient($this->cache_key, $info, $this->cache_ttl);
        return $info;
    }
}
