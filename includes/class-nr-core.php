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

        // Переопределяем текст рейтинга WooCommerce и добавляем значок примечания
        add_filter('woocommerce_product_get_rating_html', [$this, 'custom_rating_html'], 10, 3);
        add_filter('woocommerce_locate_template', [$this, 'override_wc_templates'], 10, 3);
        add_action('woocommerce_after_shop_loop_item_title', [$this, 'editor_note_badge_in_loop'], 6);
        add_action('woocommerce_single_product_summary', [$this, 'editor_note_badge_single'], 6);

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
                'post_title'     => __('Editor login', 'woocommerce-product-reviews'),
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

    /**
     * Является ли пользователь редактором или администратором (для пометки комментариев).
     */
    public static function is_editor_or_admin($user = null) {
        $u = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$u || empty($u->ID)) {
            return false;
        }
        if (user_can($u, 'manage_options')) {
            return true;
        }
        return in_array('editor', (array) $u->roles, true);
    }

    /**
     * Получить заголовок примечания редактора из настроек.
     */
    public static function get_editor_note_title() {
        $title = self::instance()->get_option('editor_note_title', '');
        return $title ? $title : __('Примечание редактора', 'woocommerce-product-reviews');
    }

    /**
     * Переопределяем HTML рейтинга WooCommerce: убираем "клиента/пользователя".
     */
    public function custom_rating_html($html, $rating, $count) {
        if ($rating <= 0) {
            return $html;
        }
        $label = sprintf('Рейтинг %s из 5', number_format_i18n($rating, 2));
        $width = ($rating / 5) * 100;
        $html  = '<div class="star-rating" role="img" aria-label="' . esc_attr($label) . '">';
        $html .= '<span style="width:' . esc_attr($width) . '%">' . esc_html($label) . '</span>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Русское склонение: 1 отзыв, 2 отзыва, 5 отзывов.
     */
    public static function plural_reviews($count) {
        $count = (int) $count;
        $mod10 = $count % 10;
        $mod100 = $count % 100;
        if ($mod10 === 1 && $mod100 !== 11) {
            return $count . ' отзыв';
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            return $count . ' отзыва';
        }
        return $count . ' отзывов';
    }

    /**
     * Подменяем шаблон rating.php из WooCommerce на наш.
     */
    public function override_wc_templates($template, $template_name, $template_path) {
        if ($template_name === 'single-product/rating.php') {
            $plugin_template = NR_PATH . 'templates/single-product/rating.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    /**
     * Значок примечания редактора в списке товаров (каталог/архив).
     */
    public function editor_note_badge_in_loop() {
        global $product;
        if (!$product) {
            return;
        }
        $note = get_post_meta($product->get_id(), '_nr_editor_note', true);
        if (!is_string($note) || trim(wp_strip_all_tags($note)) === '') {
            return;
        }
        echo '<span class="nr-editor-note-icon" title="' . esc_attr__('Есть примечание редактора', 'woocommerce-product-reviews') . '">📝</span>';
    }

    /**
     * Значок примечания редактора на странице товара (под заголовком).
     */
    public function editor_note_badge_single() {
        global $product;
        if (!$product) {
            return;
        }
        $note = get_post_meta($product->get_id(), '_nr_editor_note', true);
        if (!is_string($note) || trim(wp_strip_all_tags($note)) === '') {
            return;
        }
        echo '<a href="#nr-editor-note" class="nr-editor-note-link" title="' . esc_attr__('Перейти к примечанию редактора', 'woocommerce-product-reviews') . '">';
        echo '<span class="nr-editor-note-icon">📝</span> ';
        echo '<span>' . esc_html__('Примечание редактора', 'woocommerce-product-reviews') . '</span>';
        echo '</a>';
    }

    private function default_options() {
        return [
            'vk_app_id'     => '',
            'vk_secret'     => '',
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
            'rate_limit_count' => 5,
            'rate_limit_period' => 3600,
            'editor_smilies' => 1,
            'comments_per_page' => 10,
            'editor_login_redirect' => '',
            'editor_login_page_id' => 0,
            'editor_note_title' => '',
        ];
    }
}
