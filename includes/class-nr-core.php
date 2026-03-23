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
        // Merge stored options with defaults so new keys are always available
        $this->options = wp_parse_args(get_option('nr_options', []), $this->default_options());
    }

    public function init() {
        require_once NR_PATH . 'includes/class-nr-comments.php';
        require_once NR_PATH . 'includes/class-nr-social.php';
        require_once NR_PATH . 'includes/class-nr-shortcodes.php';
        require_once NR_PATH . 'includes/class-nr-rating.php';

        NR_Comments::instance()->init();
        NR_Social::instance()->init();
        NR_Shortcodes::instance()->init();
        NR_Rating::instance()->init();

        // Не индексировать секретную страницу входа редактора
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

        // Создаём секретную страницу входа редактора, если ещё нет
        if (empty($options['editor_login_page_id']) || !get_post((int) $options['editor_login_page_id'])) {
            $secret_slug = 'editor-login-' . strtolower(wp_generate_password(16, false, false));
            $page_id = wp_insert_post([
                'post_title'     => 'Вход редактора',
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

    /**
     * Пользователь является именно редактором (роль editor), без админов.
     */
    public static function is_editor_user($user = null) {
        $u = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$u || empty($u->ID)) {
            return false;
        }
        if (user_can($u, 'manage_options')) {
            return false;
        }
        return in_array('editor', (array) $u->roles, true);
    }

    /**
     * Может редактировать примечания: редактор или администратор.
     */
    public static function can_manage_editor_notes($user = null) {
        $u = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$u || empty($u->ID)) {
            return false;
        }
        if (user_can($u, 'manage_options')) {
            return true;
        }
        return in_array('editor', (array) $u->roles, true);
    }

    private function default_options() {
        return [
            'vk_app_id'     => '',
            'yandex_id'     => '',
            'yandex_secret' => '',
            'enable_vk'     => 0,
            'enable_yandex' => 0,
            'enable_ok'     => 0,
            'ok_app_id'     => '',
            'ok_app_key'    => '',
            'ok_secret'     => '',
            'enable_google' => 0,
            'google_id'     => '',
            'google_secret' => '',
            'thread_depth'  => 1,
            'editor_smilies' => 1,
            'comments_per_page' => 10,
            'editor_login_redirect' => '',
            'editor_login_page_id' => 0,
        ];
    }
}
