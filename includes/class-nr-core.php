<?php
/**
 * Core plugin class.
 *
 * Handles plugin initialization, activation, option management,
 * and loading of all sub-modules (comments, shortcodes, admin).
 *
 * @package SmartProductReviews
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NR_Core
 *
 * Singleton core class that bootstraps the entire plugin.
 *
 * @since 1.0.0
 */
final class NR_Core {

    /**
     * Singleton instance.
     *
     * @since 1.0.0
     * @var NR_Core|null
     */
    private static $instance = null;

    /**
     * Plugin options array.
     *
     * @since 1.0.0
     * @var array
     */
    private $options = [];

    /**
     * Get the singleton instance of the core class.
     *
     * @since  1.0.0
     * @return NR_Core
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * Loads saved options from the database, merging with defaults.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->options = get_option('nr_options', $this->default_options());
    }

    /**
     * Initialize all plugin modules.
     *
     * Loads comments, shortcodes, and (on admin pages) the admin module.
     * Also hooks the noindex meta tag for the editor login page.
     *
     * @since  1.0.0
     * @return void
     */
    public function init() {
        require_once NR_PATH . 'includes/class-nr-comments.php';
        require_once NR_PATH . 'includes/class-nr-shortcodes.php';

        NR_Comments::instance()->init();
        NR_Shortcodes::instance()->init();

        // Noindex the secret editor login page
        add_action('wp_head', [$this, 'noindex_editor_login_page']);

        if (is_admin()) {
            require_once NR_PATH . 'admin/class-nr-admin.php';
            NR_Admin::instance()->init();
        }
    }

    /**
     * Run activation tasks.
     *
     * Merges current options with defaults and creates the secret
     * editor login page if it does not already exist.
     *
     * @since  1.0.0
     * @return void
     */
    public function activate() {
        $defaults = $this->default_options();
        $current  = get_option('nr_options', []);
        $options  = wp_parse_args($current, $defaults);

        // Create secret editor login page if not exists
        if (empty($options['editor_login_page_id']) || !get_post((int) $options['editor_login_page_id'])) {
            $secret_slug = 'editor-login-' . strtolower(wp_generate_password(16, false, false));
            $page_id = wp_insert_post([
                'post_title'     => 'Editor Login',
                'post_name'      => $secret_slug,
                'post_content'   => '[nr_editor_login]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
            ]);
            if (!is_wp_error($page_id) && $page_id) {
                $options['editor_login_page_id'] = (int) $page_id;
            }
        }

        update_option('nr_options', $options);
    }

    /**
     * Retrieve a single plugin option by key.
     *
     * @since  1.0.0
     * @param  string $key     Option key.
     * @param  mixed  $default Default value if the key is not set.
     * @return mixed Option value or default.
     */
    public function get_option($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    /**
     * Retrieve all plugin options.
     *
     * @since  1.0.0
     * @return array Full options array.
     */
    public function get_options() {
        return $this->options;
    }

    /**
     * Output a noindex meta tag on the editor login page.
     *
     * Prevents search engines from indexing the secret login page.
     *
     * @since  1.0.0
     * @return void
     */
    public function noindex_editor_login_page() {
        $page_id = isset($this->options['editor_login_page_id']) ? (int) $this->options['editor_login_page_id'] : 0;
        if ($page_id && is_page($page_id)) {
            echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
        }
    }

    /**
     * Return default plugin options.
     *
     * @since  1.0.0
     * @return array Default options.
     */
    private function default_options() {
        return [
            'editor_smilies'        => 1,
            'comments_per_page'     => 10,
            'editor_login_redirect' => '',
            'editor_login_page_id'  => 0,
        ];
    }
}
