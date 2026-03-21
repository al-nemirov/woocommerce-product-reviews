<?php
/**
 * Comments and reviews handler.
 *
 * Manages product review display, AJAX submission, editor note editing,
 * review tab replacement, and asset enqueuing.
 *
 * @package SmartProductReviews
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NR_Comments
 *
 * Singleton class responsible for the review/comment system
 * and editor note functionality on WooCommerce product pages.
 *
 * @since 1.0.0
 */
class NR_Comments {

    /**
     * Singleton instance.
     *
     * @since 1.0.0
     * @var NR_Comments|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @since  1.0.0
     * @return NR_Comments
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register all WordPress hooks and shortcodes.
     *
     * @since  1.0.0
     * @return void
     */
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
     * AJAX handler: return editor note status for the status bar.
     *
     * Responds with JSON indicating whether the current user is a logged-in
     * note editor (not an admin, who has the WP toolbar instead).
     *
     * @since  1.0.0
     * @return void Outputs JSON and exits.
     */
    public function ajax_editor_status() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $user = wp_get_current_user();
        $can_edit = $user->ID && current_user_can('manage_review_notes');
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

    /**
     * AJAX handler: save an editor note for a product.
     *
     * Validates nonce, checks user capabilities, sanitizes content,
     * and updates post meta for the editor note.
     *
     * @since  1.0.0
     * @return void Outputs JSON response and exits.
     */
    public function ajax_save_editor_note() {
        check_ajax_referer('nr_save_editor_note', 'nonce');
        if ( ! current_user_can( 'manage_review_notes' ) ) {
            wp_send_json_error(['message' => 'Access denied.']);
        }
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        if (!$post_id || get_post_type($post_id) !== 'product') {
            wp_send_json_error(['message' => 'Invalid product.']);
        }

        $user         = wp_get_current_user();
        $old_content  = get_post_meta( $post_id, '_nr_editor_note', true );
        $this->log_editor_note_change( $post_id, $user, $old_content, $content );

        update_post_meta($post_id, '_nr_editor_note', $content);
        update_post_meta($post_id, '_nr_editor_note_author', $user->display_name);
        wp_send_json_success(['message' => 'Note saved.']);
    }

    /**
     * Handle non-AJAX editor note form submission on the frontend.
     *
     * Processes the POST request from the editor note form, validates nonce
     * and capabilities, saves the note, and redirects back to the product page.
     *
     * @since  1.0.0
     * @return void Redirects and exits on form submission.
     */
    public function maybe_save_editor_note_form() {
        if (empty($_POST['nr_editor_note_form'])) {
            return;
        }
        if (empty($_POST['nr_editor_nonce']) || !wp_verify_nonce($_POST['nr_editor_nonce'], 'nr_save_editor_note')) {
            wp_safe_redirect(add_query_arg('nr_note_error', 'nonce', wp_get_referer() ?: home_url('/')));
            exit;
        }
        if ( ! current_user_can( 'manage_review_notes' ) ) {
            wp_safe_redirect(add_query_arg('nr_note_error', 'cap', wp_get_referer() ?: home_url('/')));
            exit;
        }
        $post_id = isset($_POST['nr_editor_note_post']) ? (int) $_POST['nr_editor_note_post'] : 0;
        if (!$post_id || get_post_type($post_id) !== 'product') {
            wp_safe_redirect(add_query_arg('nr_note_error', 'post', wp_get_referer() ?: home_url('/')));
            exit;
        }
        $content     = isset($_POST['nr_editor_note_content']) ? wp_kses_post(wp_unslash($_POST['nr_editor_note_content'])) : '';
        $user        = wp_get_current_user();
        $old_content = get_post_meta( $post_id, '_nr_editor_note', true );
        $this->log_editor_note_change( $post_id, $user, $old_content, $content );

        update_post_meta($post_id, '_nr_editor_note', $content);
        update_post_meta($post_id, '_nr_editor_note_author', $user->display_name);
        wp_safe_redirect(get_permalink($post_id));
        exit;
    }

    /**
     * Shortcode callback to render the reviews block on a product page.
     *
     * Usage: [nr_product_reviews] or [nr_product_reviews id="123"]
     * Also handles [nr_editor_note] shortcode (same output).
     *
     * @since  1.0.0
     * @param  array|string $atts Shortcode attributes. Supports 'id' for product ID.
     * @return string Rendered HTML or empty string.
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
     * Render the reviews block via do_action hook.
     *
     * Hooked to 'nr_single_product_reviews'. Outputs reviews HTML
     * for the current product page.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_product_reviews() {
        $product_id = is_singular('product') ? get_the_ID() : 0;
        if ($product_id && get_post_type($product_id) === 'product') {
            echo $this->render_product_reviews_html($product_id);
        }
    }

    /**
     * Build the full HTML reviews block for a given product.
     *
     * Loads the comments template, sets up post data for the target product,
     * captures output via output buffering, and restores the original post.
     *
     * @since  1.0.0
     * @param  int $product_id WooCommerce product ID.
     * @return string Rendered HTML string, or empty string on failure.
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

    /**
     * Replace the default WooCommerce reviews tab with the editor note tab.
     *
     * Removes the standard 'reviews' tab and adds a custom 'nr_reviews' tab
     * that loads the plugin's comments template.
     *
     * @since  1.0.0
     * @param  array $tabs WooCommerce product tabs array.
     * @return array Modified tabs array.
     */
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

    /**
     * Override the comments template for product pages.
     *
     * Points to the plugin's custom comments template instead of the theme default.
     *
     * @since  1.0.0
     * @param  string $template Path to the current comments template.
     * @return string Path to the plugin's comments template, or the original.
     */
    public function comments_template($template) {
        if (!is_singular('product')) {
            return $template;
        }
        $file = NR_PATH . 'templates/comments.php';
        return file_exists($file) ? $file : $template;
    }

    /**
     * Enqueue the editor status bar CSS and JS.
     *
     * Loaded on all frontend pages (except admin and Elementor editor)
     * so note editors see their status bar. The bar itself is populated via AJAX.
     *
     * @since  1.0.0
     * @return void
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

    /**
     * Enqueue review form CSS and JS assets.
     *
     * Loaded on product pages or when forced (e.g., via shortcode on non-product pages).
     * Skipped in Elementor editor/preview mode.
     *
     * @since  1.0.0
     * @param  bool $force Whether to force enqueue regardless of page type.
     * @return void
     */
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

    /**
     * Check if the current request is within an Elementor editor or preview context.
     *
     * @since  1.0.0
     * @return bool True if in Elementor editor or preview mode.
     */
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

    /**
     * Customize WordPress comment form defaults for product pages.
     *
     * Changes the reply title and submit button label.
     *
     * @since  1.0.0
     * @param  array $defaults Default comment form arguments.
     * @return array Modified defaults.
     */
    public function form_defaults($defaults) {
        if (!is_singular('product')) {
            return $defaults;
        }
        $defaults['title_reply'] = 'Leave a Review';
        $defaults['label_submit'] = 'Submit';
        return $defaults;
    }

    /**
     * AJAX handler: submit a new product review.
     *
     * Validates nonce, sanitizes input, checks minimum content length,
     * inserts the comment, and saves the star rating as comment meta.
     *
     * @since  1.0.0
     * @return void Outputs JSON response and exits.
     */
    public function ajax_submit() {
        check_ajax_referer('nr_comment', 'nonce');

        // Rate limit: max 5 review submissions per IP per hour.
        $ip        = nr_get_client_ip();
        $cache_key = 'nr_submit_rl_' . md5( $ip );
        $attempts  = (int) get_transient( $cache_key );
        if ( $attempts >= 5 ) {
            wp_send_json_error( [ 'message' => 'Too many reviews submitted. Please try again later.' ] );
        }
        set_transient( $cache_key, $attempts + 1, HOUR_IN_SECONDS );

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
            'comment_post_ID'      => $post_id,
            'comment_author'       => $author,
            'comment_author_email' => $email,
            'comment_content'      => wp_kses_post($content),
            'user_id'              => $user->ID,
            'comment_author_IP'    => nr_get_client_ip(),
            'comment_agent'        => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 254) : '',
        ];

        // Logged-in users are auto-approved; guests go through normal WP moderation.
        if ($user->ID) {
            $comment_data['comment_approved'] = 1;
        } else {
            $comment_data['comment_approved'] = wp_allow_comment($comment_data);
        }

        $comment_id = wp_insert_comment($comment_data);
        if (!$comment_id) {
            wp_send_json_error(['message' => 'Error saving.']);
        }

        if ($rating >= 1 && $rating <= 5) {
            add_comment_meta($comment_id, 'rating', $rating, true);
        }

        $held_for_moderation = isset($comment_data['comment_approved']) && $comment_data['comment_approved'] !== 1 && $comment_data['comment_approved'] !== '1';
        $message = $held_for_moderation
            ? 'Thank you! Your review has been submitted and is awaiting moderation.'
            : 'Thank you! Your review has been submitted.';

        wp_send_json_success([
            'message'    => $message,
            'comment_id' => $comment_id,
        ]);
    }

    /**
     * Retrieve a list of approved comments for a product.
     *
     * @since  1.0.0
     * @param  int   $post_id Product post ID.
     * @param  array $args    Optional. Additional arguments for get_comments().
     * @return array Array of WP_Comment objects.
     */
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

    /**
     * Log an editor-note change for audit purposes.
     *
     * Stores a timestamped entry in the '_nr_editor_note_audit_log' post meta
     * array. Each entry records who made the change, when, the action type
     * (added, modified, or deleted), and the previous/new content.
     * The log is capped at the 50 most recent entries per product.
     *
     * @since  1.0.1
     * @param  int     $post_id     Product post ID.
     * @param  WP_User $user        The user making the change.
     * @param  string  $old_content Previous note content.
     * @param  string  $new_content New note content.
     * @return void
     */
    private function log_editor_note_change( $post_id, $user, $old_content, $new_content ) {
        $old_content = is_string( $old_content ) ? $old_content : '';
        $new_content = is_string( $new_content ) ? $new_content : '';

        // Determine the action type.
        if ( empty( $old_content ) && ! empty( $new_content ) ) {
            $action = 'added';
        } elseif ( ! empty( $old_content ) && empty( $new_content ) ) {
            $action = 'deleted';
        } elseif ( $old_content !== $new_content ) {
            $action = 'modified';
        } else {
            // No actual change — skip logging.
            return;
        }

        $log = get_post_meta( $post_id, '_nr_editor_note_audit_log', true );
        if ( ! is_array( $log ) ) {
            $log = [];
        }

        $log[] = [
            'user_id'      => $user->ID,
            'user_login'   => $user->user_login,
            'display_name' => $user->display_name,
            'action'       => $action,
            'old_content'  => $old_content,
            'new_content'  => $new_content,
            'timestamp'    => current_time( 'mysql' ),
            'timestamp_gmt'=> current_time( 'mysql', true ),
            'ip'           => nr_get_client_ip(),
        ];

        // Keep only the 50 most recent entries.
        if ( count( $log ) > 50 ) {
            $log = array_slice( $log, -50 );
        }

        update_post_meta( $post_id, '_nr_editor_note_audit_log', $log );
    }
}
