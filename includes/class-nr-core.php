<?php
if (!defined('ABSPATH')) {
    exit;
}

final class NR_Core {

    private static $instance = null;
    private $options = [];

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option('nr_options', $this->default_options());
    }

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

    public function get_option($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    public function get_options() {
        return $this->options;
    }

    public function noindex_editor_login_page() {
        $page_id = isset($this->options['editor_login_page_id']) ? (int) $this->options['editor_login_page_id'] : 0;
        if ($page_id && is_page($page_id)) {
            echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
        }
    }

    private function default_options() {
        return [
            'editor_smilies'        => 1,
            'comments_per_page'     => 10,
            'editor_login_redirect' => '',
            'editor_login_page_id'  => 0,
        ];
    }
}
