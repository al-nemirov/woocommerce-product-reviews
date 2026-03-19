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
     * AJAX: editor note status (for the status bar).
     * Status bar shown only for note editors, not admins.
     */
    public function ajax_editor_status() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $user = wp_get_current_user();
        $can_edit = $user->ID && (
            current_user_can('edit_posts') || current_user_can('edit_products') || current_user_can('edit_pages')
            || in_array('editor', (array) $user->roles) || in_array('administrator', (array) $user->roles)
        );
        // Admins don't need this — they have the WP toolbar.
        if (!$can_edit || current_user_can('manage_options')) {
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
        $can = current_user_can('edit_posts') || current_user_can('edit_products') || current_user_can('edit_pages') || in_array('editor', (array) $user->roles) || in_array('administrator', (array) $user->roles);
        if (!$can) {
            wp_send_json_error(['message' => 'Access denied.']);
        }
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        if (!$post_id || get_post_type($post_id) !== 'product') {
            wp_send_json_error(['message' => 'Invalid product.']);
        }
        update_post_meta($post_id, '_nr_editor_note', $content);
        update_post_meta($post_id, '_nr_editor_note_author', $user->display_name);
        wp_send_json_success(['message' => 'Note saved.']);
    }

    /**
     * Handle regular form submission (non-AJAX) on the frontend.
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
        $can = current_user_can('edit_posts') || current_user_can('edit_products') || current_user_can('edit_pages') || in_array('editor', (array) $user->roles) || in_array('administrator', (array) $user->roles);
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
     * Shortcode to render the reviews block on a product page.
     * Usage: [nr_product_reviews] or [nr_product_reviews id="123"]
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
     * Render reviews block via do_action hook.
     */
    public function render_product_reviews() {
        $product_id = is_singular('product') ? get_the_ID() : 0;
        if ($product_id && get_post_type($product_id) === 'product') {
            echo $this->render_product_reviews_html($product_id);
        }
    }

    /**
     * Render HTML reviews block for a given product.
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
            'title'    => 'Editor Note',
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
     * Editor status bar — loaded via AJAX, cache-safe.
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
        $defaults['title_reply'] = 'Leave a Review';
        $defaults['label_submit'] = 'Submit';
        return $defaults;
    }

    public function ajax_submit() {
        check_ajax_referer('nr_comment', 'nonce');
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
        $rating  = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;

        if (!$post_id || get_post_type($post_id) !== 'product') {
            wp_send_json_error(['message' => 'Invalid product.']);
        }
        if (strlen($content) < 10) {
            wp_send_json_error(['message' => 'Review text must be at least 10 characters.']);
        }

        $user = wp_get_current_user();
        $author = $user->ID ? $user->display_name : (isset($_POST['author']) ? sanitize_text_field($_POST['author']) : '');
        $email = $user->ID ? $user->user_email : (isset($_POST['email']) ? sanitize_email($_POST['email']) : '');

        if (!$user->ID && (!$author || !$email)) {
            wp_send_json_error(['message' => 'Please enter your name and email, or log in.']);
        }

        $comment_data = [
            'comment_post_ID'  => $post_id,
            'comment_author'   => $author,
            'comment_author_email' => $email,
            'comment_content'  => wp_kses_post($content),
            'comment_approved' => 1,
            'user_id'          => $user->ID,
        ];

        $comment_id = wp_insert_comment($comment_data);
        if (!$comment_id) {
            wp_send_json_error(['message' => 'Error saving.']);
        }

        if ($rating >= 1 && $rating <= 5) {
            add_comment_meta($comment_id, 'rating', $rating, true);
        }

        wp_send_json_success([
            'message' => 'Thank you! Your review has been submitted.',
            'comment_id' => $comment_id,
        ]);
    }

    public static function get_comments_list($post_id, $args = []) {
        $defaults = [
            'post_id' => $post_id,
            'status'  => 'approve',
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
            'number'  => spr_instance()->get_option('comments_per_page', 10),
        ];
        return get_comments(array_merge($defaults, $args));
    }
}
