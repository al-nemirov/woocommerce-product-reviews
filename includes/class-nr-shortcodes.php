<?php
/**
 * Shortcodes handler.
 *
 * Registers and renders shortcodes for the editor login form,
 * latest reviews widget, and popular reviews widget.
 * Also handles the editor login form submission with IP blocking.
 *
 * @package SmartProductReviews
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NR_Shortcodes
 *
 * Singleton class that provides all non-review shortcodes
 * and handles editor authentication.
 *
 * @since 1.0.0
 */
class NR_Shortcodes {

    /**
     * Singleton instance.
     *
     * @since 1.0.0
     * @var NR_Shortcodes|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @since  1.0.0
     * @return NR_Shortcodes
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register shortcodes and hooks.
     *
     * @since  1.0.0
     * @return void
     */
    public function init() {
        add_shortcode('nr_popular_comments', [$this, 'popular_comments']);
        add_shortcode('nr_latest_comments', [$this, 'latest_comments']);
        add_shortcode('nr_editor_login', [$this, 'editor_login']);
        add_action('template_redirect', [$this, 'maybe_editor_login'], 5);
    }

    /**
     * Clear all editor login IP blocks.
     *
     * Called from the admin settings page to unblock editors
     * who have been locked out after too many failed attempts.
     *
     * @since  1.0.0
     * @return void
     */
    public static function clear_login_blocks() {
        delete_option('nr_editor_login_blocks');
    }

    /**
     * Handle editor login form submission (runs before page output).
     *
     * Validates nonce, checks IP block status, authenticates the user,
     * verifies editor capabilities, and sets auth cookies on success.
     * Blocks the IP for 1 hour after 3 failed attempts.
     *
     * @since  1.0.0
     * @return void Redirects and exits on form submission.
     */
    public function maybe_editor_login() {
        if (empty($_POST['nr_editor_login']) || empty($_POST['nr_editor_nonce'])) {
            return;
        }
        $ip   = nr_get_client_ip();
        $hash = md5( $ip );
        $blocks = get_option('nr_editor_login_blocks', []);

        // On error always redirect to editor login page
        $login_page_url = '';
        $page_id = spr_instance()->get_option('editor_login_page_id');
        if ($page_id && get_post($page_id)) {
            $login_page_url = get_permalink($page_id);
        }
        $error_redirect = $login_page_url ?: (wp_get_referer() ?: home_url('/'));

        // Rate limit: max 10 login attempts per IP per 15 minutes.
        $rl_key     = 'nr_login_rl_' . $hash;
        $rl_count   = (int) get_transient( $rl_key );
        if ( $rl_count >= 10 ) {
            wp_safe_redirect( add_query_arg( 'nr_login_error', 'blocked', $error_redirect ) );
            exit;
        }
        set_transient( $rl_key, $rl_count + 1, 15 * MINUTE_IN_SECONDS );

        // Block for 1 hour after 3 failed attempts
        if (!empty($blocks[$hash]['expiry']) && (int) $blocks[$hash]['expiry'] > time()) {
            wp_safe_redirect(add_query_arg('nr_login_error', 'blocked', $error_redirect));
            exit;
        }

        if (!wp_verify_nonce($_POST['nr_editor_nonce'], 'nr_editor_login')) {
            wp_safe_redirect(add_query_arg('nr_login_error', 'nonce', $error_redirect));
            exit;
        }
        $login = isset($_POST['nr_editor_user']) ? trim(sanitize_text_field(wp_unslash($_POST['nr_editor_user']))) : '';
        $password = isset($_POST['nr_editor_pass']) ? $_POST['nr_editor_pass'] : '';
        $redirect_to = isset($_POST['nr_editor_redirect']) ? esc_url_raw(wp_unslash($_POST['nr_editor_redirect'])) : '';

        /**
         * Record a failed login attempt and update the IP block counter.
         *
         * @since 1.0.0
         */
        $record_fail = function () use ($hash, &$blocks) {
            if (!isset($blocks[$hash])) {
                $blocks[$hash] = ['attempts' => 0, 'expiry' => 0];
            }
            $blocks[$hash]['attempts'] = (int) ($blocks[$hash]['attempts'] ?? 0) + 1;
            if ($blocks[$hash]['attempts'] >= 3) {
                $blocks[$hash]['expiry'] = time() + HOUR_IN_SECONDS;
            }
            update_option('nr_editor_login_blocks', $blocks, false);
        };

        if (!$login || !$password) {
            $record_fail();
            wp_safe_redirect(add_query_arg('nr_login_error', 'empty', $error_redirect));
            exit;
        }

        $user = wp_signon([
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => !empty($_POST['nr_editor_remember']),
        ], is_ssl());

        if (is_wp_error($user)) {
            $record_fail();
            wp_safe_redirect(add_query_arg('nr_login_error', 'invalid', $error_redirect));
            exit;
        }

        if ( ! user_can( $user, 'manage_review_notes' ) ) {
            wp_logout();
            $record_fail();
            wp_safe_redirect(add_query_arg('nr_login_error', 'forbidden', $error_redirect));
            exit;
        }

        // Successful login — clear block and counter for this IP
        if (isset($blocks[$hash])) {
            unset($blocks[$hash]);
            update_option('nr_editor_login_blocks', $blocks, false);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, !empty($_POST['nr_editor_remember']));

        if ($redirect_to) {
            wp_safe_redirect($redirect_to);
        } else {
            wp_safe_redirect(spr_instance()->get_option('editor_login_redirect', home_url('/')));
        }
        exit;
    }

    /**
     * Render the editor login form shortcode.
     *
     * Displays a login form for note editors, or a welcome message
     * with links if the user is already logged in with edit capabilities.
     *
     * Usage: [nr_editor_login] or [nr_editor_login redirect="https://site.com/shop/"]
     *
     * @since  1.0.0
     * @param  array|string $atts Shortcode attributes. Supports 'redirect' URL.
     * @return string Rendered HTML for the login form or logged-in state.
     */
    public function editor_login($atts) {
        $atts = shortcode_atts([
            'redirect' => '',
        ], $atts, 'nr_editor_login');

        $user = wp_get_current_user();
        $can_edit_notes = is_user_logged_in() && current_user_can( 'manage_review_notes' );
        if ($can_edit_notes) {
            $redirect = $atts['redirect'] ? esc_url($atts['redirect']) : (spr_instance()->get_option('editor_login_redirect') ?: home_url('/'));
            $sample = get_posts(['post_type' => 'product', 'posts_per_page' => 1, 'post_status' => 'publish']);
            $book_link = !empty($sample) ? get_permalink($sample[0]) : $redirect;
            ob_start();
            ?>
            <div class="nr-editor-login nr-editor-login--logged">
                <p><strong>You are logged in as <?php echo esc_html($user->display_name); ?>.</strong></p>
                <p>Go to any product page — you will see the Editor Note block and the Edit Note button.</p>
                <p>
                    <a href="<?php echo esc_url($book_link); ?>" class="nr-editor-login__link">Open sample product</a> &nbsp;|&nbsp;
                    <a href="<?php echo esc_url($redirect); ?>" class="nr-editor-login__link">Go to site</a> &nbsp;|&nbsp;
                    <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="nr-editor-login__link">Log out</a>
                </p>
            </div>
            <?php
            return ob_get_clean();
        }

        $error = isset($_GET['nr_login_error']) ? sanitize_text_field($_GET['nr_login_error']) : '';
        $messages = [
            'nonce'     => 'Security error. Please try again.',
            'empty'     => 'Please enter username and password.',
            'invalid'   => 'Invalid username or password. Use the username from your WordPress profile. If forgotten, ask the administrator to reset it.',
            'forbidden' => 'This user does not have permission to edit notes.',
            'blocked'   => 'Too many failed attempts. Please try again in one hour.',
        ];
        $message = isset($messages[$error]) ? $messages[$error] : '';

        $redirect_to = $atts['redirect'] ? esc_url($atts['redirect']) : (spr_instance()->get_option('editor_login_redirect') ?: '');
        if (!$redirect_to) {
            $redirect_to = home_url('/');
        }

        ob_start();
        ?>
        <div class="nr-editor-login">
            <h3 class="nr-editor-login__title">Editor Notes Login</h3>
            <?php if ($message) : ?>
                <p class="nr-editor-login__error"><?php echo esc_html($message); ?><?php if ($error === 'blocked') : ?> The administrator can reset the block in Smart Product Reviews settings.<?php endif; ?></p>
            <?php endif; ?>
            <form method="post" action="" class="nr-editor-login__form">
                <?php wp_nonce_field('nr_editor_login', 'nr_editor_nonce'); ?>
                <input type="hidden" name="nr_editor_login" value="1" />
                <input type="hidden" name="nr_editor_redirect" value="<?php echo esc_attr($redirect_to); ?>" />
                <p>
                    <label for="nr_editor_user">Username</label><br>
                    <input type="text" name="nr_editor_user" id="nr_editor_user" class="nr-editor-login__input" required autocomplete="username" />
                </p>
                <p>
                    <label for="nr_editor_pass">Password</label><br>
                    <input type="password" name="nr_editor_pass" id="nr_editor_pass" class="nr-editor-login__input" required autocomplete="current-password" />
                </p>
                <p>
                    <label><input type="checkbox" name="nr_editor_remember" value="1" /> Remember me</label>
                </p>
                <p><button type="submit" class="nr-editor-login__submit">Log in</button></p>
            </form>
        </div>
        <style>
            .nr-editor-login { max-width: 360px; margin: 2em auto; padding: 24px; border: 1px solid #ddd; border-radius: 8px; background: #fafafa; }
            .nr-editor-login__title { margin-top: 0; margin-bottom: 16px; font-size: 1.25em; }
            .nr-editor-login__error { color: #c00; margin-bottom: 12px; }
            .nr-editor-login__form label { display: block; margin-bottom: 4px; font-weight: 600; }
            .nr-editor-login__input { width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
            .nr-editor-login__submit { padding: 10px 24px; background: #07B290; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
            .nr-editor-login__submit:hover { background: #059377; }
            .nr-editor-login--logged p { margin: 0 0 10px; }
            .nr-editor-login__link { color: #07B290; }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the popular reviews shortcode.
     *
     * Displays a list of the most recent approved reviews across all products,
     * sorted by date (most recent first).
     *
     * Usage: [nr_popular_comments count="5" title="Popular Reviews"]
     *
     * @since  1.0.0
     * @param  array|string $atts Shortcode attributes. Supports 'count' and 'title'.
     * @return string Rendered HTML for the popular reviews widget.
     */
    public function popular_comments($atts) {
        $atts = shortcode_atts([
            'count' => 5,
            'title' => 'Popular Reviews',
        ], $atts, 'nr_popular_comments');

        $product_ids = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ]);
        $comments = [];
        if (!empty($product_ids)) {
            $comments = get_comments([
                'post_id' => $product_ids,
                'status'  => 'approve',
                'number'  => (int) $atts['count'],
                'orderby' => 'comment_date_gmt',
                'order'   => 'DESC',
            ]);
        }

        ob_start();
        echo '<div class="nr-widget nr-popular-comments">';
        echo '<h3 class="nr-widget-title">' . esc_html($atts['title']) . '</h3>';
        echo '<ul class="nr-comment-list">';
        foreach ($comments as $c) {
            $product = get_the_title($c->comment_post_ID);
            $link = get_permalink($c->comment_post_ID);
            echo '<li class="nr-widget-item">';
            echo ' <a href="' . esc_url($link) . '#comment-' . esc_attr($c->comment_ID) . '">' . esc_html(wp_trim_words($c->comment_content, 15)) . '</a>';
            echo ' <span class="nr-meta">' . esc_html($c->comment_author) . ' · ' . esc_html($product) . '</span>';
            echo '</li>';
        }
        echo '</ul></div>';
        return ob_get_clean();
    }

    /**
     * Render the latest reviews shortcode.
     *
     * Displays a list of the most recent approved reviews across all products.
     *
     * Usage: [nr_latest_comments count="5" title="Latest Reviews"]
     *
     * @since  1.0.0
     * @param  array|string $atts Shortcode attributes. Supports 'count' and 'title'.
     * @return string Rendered HTML for the latest reviews widget.
     */
    public function latest_comments($atts) {
        $atts = shortcode_atts([
            'count' => 5,
            'title' => 'Latest Reviews',
        ], $atts, 'nr_latest_comments');

        $product_ids = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ]);
        if (empty($product_ids)) {
            $comments = [];
        } else {
            $comments = get_comments([
                'post_id' => $product_ids,
                'status'   => 'approve',
                'number'   => (int) $atts['count'],
                'orderby'  => 'comment_date_gmt',
                'order'    => 'DESC',
            ]);
        }

        ob_start();
        echo '<div class="nr-widget nr-latest-comments">';
        echo '<h3 class="nr-widget-title">' . esc_html($atts['title']) . '</h3>';
        echo '<ul class="nr-comment-list">';
        foreach ($comments as $c) {
            $product = get_the_title($c->comment_post_ID);
            $link = get_permalink($c->comment_post_ID);
            echo '<li class="nr-widget-item">';
            echo ' <a href="' . esc_url($link) . '#comment-' . esc_attr($c->comment_ID) . '">' . esc_html(wp_trim_words($c->comment_content, 15)) . '</a>';
            echo ' <span class="nr-meta">' . esc_html($c->comment_author) . ' · ' . esc_html($product) . '</span>';
            echo '</li>';
        }
        echo '</ul></div>';
        return ob_get_clean();
    }
}
