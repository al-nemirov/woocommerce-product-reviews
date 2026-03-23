<?php
if (!defined('ABSPATH')) {
    exit;
}

class NR_Comments {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_filter('comments_template', [$this, 'comments_template'], 99, 1);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_editor_status_bar'], 20);
        add_action('wp_ajax_nr_save_editor_note', [$this, 'ajax_save_editor_note']);
        add_action('wp_ajax_nr_submit_comment', [$this, 'ajax_submit']);
        add_action('wp_ajax_nopriv_nr_submit_comment', [$this, 'ajax_submit']);
        add_action('wp_ajax_nr_editor_status', [$this, 'ajax_editor_status']);
        add_action('wp_ajax_nopriv_nr_editor_status', [$this, 'ajax_editor_status']);
        add_action('template_redirect', [$this, 'maybe_save_editor_note_form']);
        add_filter('comment_form_defaults', [$this, 'form_defaults'], 10, 1);
        add_filter('woocommerce_product_tabs', [$this, 'replace_reviews_tab'], 98);
        add_shortcode('nr_product_reviews', [$this, 'shortcode_product_reviews']);
        add_shortcode('nr_editor_note', [$this, 'shortcode_product_reviews']);
        add_action('nr_single_product_reviews', [$this, 'render_product_reviews']);
    }

    /**
     * AJAX: статус редактора примечаний (для полоски «Вы вошли / Выйти»).
     * Плашка показывается только редакторам примечаний, не администраторам сайта.
     */
    public function ajax_editor_status() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $user = wp_get_current_user();
        if (!$user->ID || !NR_Core::is_editor_user($user)) {
            echo wp_json_encode(['logged_in' => false]);
            exit;
        }
        echo wp_json_encode([
            'logged_in'  => true,
            'can_edit'   => true,
            'name'       => $user->display_name,
            'logout_url' => wp_logout_url(add_query_arg('nr_logout', '1', home_url('/'))),
        ]);
        exit;
    }

    public function ajax_save_editor_note() {
        check_ajax_referer('nr_save_editor_note', 'nonce');
        $user = wp_get_current_user();
        $can = NR_Core::can_manage_editor_notes($user);
        if (!$can) {
            wp_send_json_error(['message' => __('Access denied.', 'smart-product-reviews')]);
        }
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        if (!$post_id || get_post_type($post_id) !== 'product') {
            wp_send_json_error(['message' => __('Invalid product.', 'smart-product-reviews')]);
        }
        $user = wp_get_current_user();
        update_post_meta($post_id, '_nr_editor_note', $content);
        update_post_meta($post_id, '_nr_editor_note_author', $user->display_name);
        wp_send_json_success(['message' => __('Editor note saved.', 'smart-product-reviews')]);
    }

    /**
     * Обработка обычной отправки формы (без AJAX) на фронте.
     */
    public function maybe_save_editor_note_form() {
        if (empty($_POST['nr_editor_note_form'])) {
            return;
        }
        if (empty($_POST['nr_editor_nonce']) || !wp_verify_nonce($_POST['nr_editor_nonce'], 'nr_save_editor_note')) {
            wp_safe_redirect(add_query_arg('nr_note_error', 'nonce', wp_get_referer() ?: home_url('/')));
            exit;
        }
        $user = wp_get_current_user();
        $can = NR_Core::can_manage_editor_notes($user);
        if (!$can) {
            wp_safe_redirect(add_query_arg('nr_note_error', 'cap', wp_get_referer() ?: home_url('/')));
            exit;
        }
        $post_id = isset($_POST['nr_editor_note_post']) ? (int) $_POST['nr_editor_note_post'] : 0;
        if (!$post_id || get_post_type($post_id) !== 'product') {
            wp_safe_redirect(add_query_arg('nr_note_error', 'post', wp_get_referer() ?: home_url('/')));
            exit;
        }
        $content = isset($_POST['nr_editor_note_content']) ? wp_kses_post(wp_unslash($_POST['nr_editor_note_content'])) : '';
        $user = wp_get_current_user();
        update_post_meta($post_id, '_nr_editor_note', $content);
        update_post_meta($post_id, '_nr_editor_note_author', $user->display_name);
        wp_safe_redirect(get_permalink($post_id));
        exit;
    }

    /**
     * Шорткод для вывода блока отзывов на странице товара.
     * Использование: [nr_product_reviews] или [nr_product_reviews id="123"]
     */
    public function shortcode_product_reviews($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'nr_product_reviews');
        $product_id = (int) $atts['id'];
        if (!$product_id && is_singular('product')) {
            $product_id = get_the_ID();
        }
        if (!$product_id || get_post_type($product_id) !== 'product') {
            return '';
        }
        return $this->render_product_reviews_html($product_id);
    }

    /**
     * Вывод блока отзывов по хукe do_action('nr_single_product_reviews')
     */
    public function render_product_reviews() {
        $product_id = is_singular('product') ? get_the_ID() : 0;
        if ($product_id && get_post_type($product_id) === 'product') {
            echo $this->render_product_reviews_html($product_id);
        }
    }

    /**
     * Рендер HTML блока отзывов для указанного товара.
     */
    public function render_product_reviews_html($product_id) {
        global $post;
        $product_id = (int) $product_id;
        if (!$product_id || get_post_type($product_id) !== 'product') {
            return '';
        }
        $this->assets(true);
        $file = NR_PATH . 'templates/comments.php';
        if (!file_exists($file)) {
            return '';
        }
        $saved_post = $post;
        $post = get_post($product_id);
        if (!$post) {
            $post = $saved_post;
            return '';
        }
        setup_postdata($post);
        ob_start();
        include $file;
        $html = ob_get_clean();
        wp_reset_postdata();
        $post = $saved_post;
        return $html;
    }

    public function replace_reviews_tab($tabs) {
        unset($tabs['reviews']);
        $tabs['nr_reviews'] = [
            'title'    => __('Editor note', 'smart-product-reviews'),
            'priority' => 30,
            'callback' => function () {
                $file = NR_PATH . 'templates/comments.php';
                if (file_exists($file)) {
                    include $file;
                }
            },
        ];
        return $tabs;
    }

    public function comments_template($template) {
        if (!is_singular('product')) {
            return $template;
        }
        $file = NR_PATH . 'templates/comments.php';
        return file_exists($file) ? $file : $template;
    }

    /**
     * Полоска «Вы вошли как … / Выйти» — запрос по AJAX, не зависит от кэша страницы.
     */
    public function enqueue_editor_status_bar() {
        if (is_admin() || self::is_elementor_editor_or_preview()) {
            return;
        }
        wp_enqueue_style('nr-editor-status', NR_URL . 'assets/css/editor-status.css', [], NR_VERSION);
        wp_enqueue_script('nr-editor-status', NR_URL . 'assets/js/editor-status.js', ['jquery'], NR_VERSION, true);
        wp_localize_script('nr-editor-status', 'nrEditorStatus', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    public function assets($force = false) {
        if (!$force && !is_singular('product')) {
            return;
        }
        if (self::is_elementor_editor_or_preview()) {
            return;
        }
        wp_enqueue_style('nr-comments', NR_URL . 'assets/css/comments.css', [], NR_VERSION);
        wp_enqueue_script('nr-comments', NR_URL . 'assets/js/comments.js', ['jquery'], NR_VERSION, true);
        wp_localize_script('nr-comments', 'nrData', [
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('nr_comment'),
            'editor_note_nonce' => wp_create_nonce('nr_save_editor_note'),
        ]);
    }

    private static function is_elementor_editor_or_preview() {
        if (is_admin() && isset($_GET['action']) && $_GET['action'] === 'elementor') {
            return true;
        }
        if (isset($_GET['elementor-preview'])) {
            return true;
        }
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return true;
        }
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
            return true;
        }
        return false;
    }

    public function form_defaults($defaults) {
        if (!is_singular('product')) {
            return $defaults;
        }
        $defaults['title_reply'] = __('Leave a review', 'smart-product-reviews');
        $defaults['label_submit'] = __('Submit', 'smart-product-reviews');
        return $defaults;
    }

    public function ajax_submit() {
        check_ajax_referer('nr_comment', 'nonce');

        // Rate limit: max 5 reviews per IP per hour
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $transient_key = 'nr_rl_' . md5( $ip );
        $count = (int) get_transient( $transient_key );
        if ( $count >= 5 ) {
            wp_send_json_error(['message' => __('Too many reviews. Please try again later.', 'smart-product-reviews')]);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
        $rating  = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;

        if (!$post_id || get_post_type($post_id) !== 'product') {
            wp_send_json_error(['message' => __('Invalid product.', 'smart-product-reviews')]);
        }
        if (strlen($content) < 10) {
            wp_send_json_error(['message' => __('Review text must be at least 10 characters.', 'smart-product-reviews')]);
        }

        $user = wp_get_current_user();
        $author = $user->ID ? $user->display_name : (isset($_POST['author']) ? sanitize_text_field( wp_unslash( $_POST['author'] ) ) : '');
        $email = $user->ID ? $user->user_email : (isset($_POST['email']) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '');

        if (!$user->ID && (!$author || !$email)) {
            wp_send_json_error(['message' => __('Enter name and email or log in.', 'smart-product-reviews')]);
        }

        // Use wp_new_comment() — respects WordPress moderation, triggers comment_post hook for rating sync
        $comment_data = [
            'comment_post_ID'      => $post_id,
            'comment_author'       => $author,
            'comment_author_email' => $email,
            'comment_content'      => $content,
            'comment_type'         => 'review',
            'user_id'              => $user->ID,
        ];

        // wp_new_comment() handles sanitization, moderation, Akismet, and fires comment_post action
        $comment_id = wp_new_comment( $comment_data, true );
        if ( is_wp_error( $comment_id ) ) {
            wp_send_json_error(['message' => $comment_id->get_error_message()]);
        }
        if ( ! $comment_id ) {
            wp_send_json_error(['message' => __('Save failed.', 'smart-product-reviews')]);
        }

        if ($rating >= 1 && $rating <= 5) {
            add_comment_meta($comment_id, 'rating', $rating, true);
            // Manually sync rating since comment_post fires before meta is saved
            NR_Rating::update_product_rating( $post_id );
        }

        // Increment rate limit counter
        set_transient( $transient_key, $count + 1, HOUR_IN_SECONDS );

        $comment = get_comment( $comment_id );
        $status_msg = ( $comment && (int) $comment->comment_approved === 1 )
            ? __('Thank you! Your review has been published.', 'smart-product-reviews')
            : __('Thank you! Your review is awaiting moderation.', 'smart-product-reviews');

        wp_send_json_success([
            'message'    => $status_msg,
            'comment_id' => $comment_id,
            'approved'   => $comment ? (int) $comment->comment_approved : 0,
        ]);
    }

    public static function get_comments_list($post_id, $args = []) {
        $defaults = [
            'post_id' => $post_id,
            'status'  => 'approve',
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
            'number'  => NR_Core::instance()->get_option('comments_per_page', 10),
        ];
        return get_comments(array_merge($defaults, $args));
    }
}
