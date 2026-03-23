<?php
if (!defined('ABSPATH')) {
    exit;
}

class NR_Shortcodes {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_shortcode('nr_popular_comments', [$this, 'popular_comments']);
        add_shortcode('nr_latest_comments', [$this, 'latest_comments']);
        add_shortcode('nr_latest_editor_notes', [$this, 'latest_editor_notes']);
        add_shortcode('nr_editor_login', [$this, 'editor_login']);
        add_action('template_redirect', [$this, 'maybe_editor_login'], 5);
        add_action('wp_ajax_nr_editor_ajax_login', [$this, 'ajax_editor_login']);
        add_action('wp_ajax_nopriv_nr_editor_ajax_login', [$this, 'ajax_editor_login']);
    }

    /**
     * Сброс всех блокировок входа редактора (по кнопке в админке).
     */
    public static function clear_login_blocks() {
        delete_option('nr_editor_login_blocks');
    }

    /**
     * Обработка формы входа редактора (до вывода страницы).
     */
    public function maybe_editor_login() {
        if (empty($_POST['nr_editor_login']) || empty($_POST['nr_editor_nonce'])) {
            return;
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $hash = md5($ip);
        $blocks = get_option('nr_editor_login_blocks', []);

        // При ошибке всегда вести на страницу входа редактора, чтобы показать форму и сообщение
        $login_page_url = '';
        $page_id = NR_Core::instance()->get_option('editor_login_page_id');
        if ($page_id && get_post($page_id)) {
            $login_page_url = get_permalink($page_id);
        }
        $error_redirect = $login_page_url ?: (wp_get_referer() ?: home_url('/'));

        // Блок на 1 час после 3 неудачных попыток
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

        if (!NR_Core::is_editor_user($user)) {
            wp_logout();
            $record_fail();
            wp_safe_redirect(add_query_arg('nr_login_error', 'forbidden', $error_redirect));
            exit;
        }

        // Успешный вход — снимаем блок и счётчик для этого IP
        if (isset($blocks[$hash])) {
            unset($blocks[$hash]);
            update_option('nr_editor_login_blocks', $blocks, false);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, !empty($_POST['nr_editor_remember']));

        if ($redirect_to) {
            wp_safe_redirect($redirect_to);
        } else {
            wp_safe_redirect(NR_Core::instance()->get_option('editor_login_redirect', home_url('/')));
        }
        exit;
    }

    /**
     * AJAX editor login — no-reload alternative.
     */
    public function ajax_editor_login() {
        check_ajax_referer('nr_editor_login', 'nonce');

        $ip   = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $hash = md5($ip);
        $blocks = get_option('nr_editor_login_blocks', []);

        if (!empty($blocks[$hash]['expiry']) && (int) $blocks[$hash]['expiry'] > time()) {
            wp_send_json_error(['message' => __('Too many failed attempts. Try again in one hour.', 'woocommerce-product-reviews')]);
        }

        $login    = isset($_POST['user']) ? trim(sanitize_text_field(wp_unslash($_POST['user']))) : '';
        $password = isset($_POST['pass']) ? $_POST['pass'] : '';
        $remember = !empty($_POST['remember']);

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
            wp_send_json_error(['message' => __('Enter login and password.', 'woocommerce-product-reviews')]);
        }

        $user = wp_signon([
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => $remember,
        ], is_ssl());

        if (is_wp_error($user)) {
            $record_fail();
            wp_send_json_error(['message' => __('Invalid login or password.', 'woocommerce-product-reviews')]);
        }

        if (!NR_Core::is_editor_user($user)) {
            wp_logout();
            $record_fail();
            wp_send_json_error(['message' => __('This user has no permission to edit editor notes.', 'woocommerce-product-reviews')]);
        }

        // Success — clear blocks
        if (isset($blocks[$hash])) {
            unset($blocks[$hash]);
            update_option('nr_editor_login_blocks', $blocks, false);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);

        $redirect = NR_Core::instance()->get_option('editor_login_redirect', home_url('/'));
        wp_send_json_success([
            'message'  => sprintf(__('Logged in as %s.', 'woocommerce-product-reviews'), $user->display_name),
            'redirect' => $redirect,
            'name'     => $user->display_name,
        ]);
    }

    /**
     * Шорткод: страница входа для редактора примечаний.
     * Использование: создайте страницу, вставьте [nr_editor_login], дайте ссылку редактору.
     */
    public function editor_login($atts) {
        $atts = shortcode_atts([
            'redirect' => '',
        ], $atts, 'nr_editor_login');

        $user = wp_get_current_user();
        $can_edit_notes = is_user_logged_in() && NR_Core::is_editor_user($user);
        if ($can_edit_notes) {
            $redirect = $atts['redirect'] ? esc_url($atts['redirect']) : (NR_Core::instance()->get_option('editor_login_redirect') ?: home_url('/'));
            $sample = get_posts(['post_type' => 'product', 'posts_per_page' => 1, 'post_status' => 'publish']);
            $book_link = !empty($sample) ? get_permalink($sample[0]) : $redirect;
            ob_start();
            ?>
            <div class="nr-editor-login nr-editor-login--logged">
                <p><strong><?php echo esc_html(sprintf(__('Logged in as %s.', 'woocommerce-product-reviews'), $user->display_name)); ?></strong></p>
                <p><?php echo esc_html__('Open any product page: at the bottom you will see the editor note block and the edit button.', 'woocommerce-product-reviews'); ?></p>
                <p>
                    <a href="<?php echo esc_url($book_link); ?>" class="nr-editor-login__link"><?php echo esc_html__('Open sample product', 'woocommerce-product-reviews'); ?></a> &nbsp;|&nbsp;
                    <a href="<?php echo esc_url($redirect); ?>" class="nr-editor-login__link"><?php echo esc_html__('Go to site', 'woocommerce-product-reviews'); ?></a> &nbsp;|&nbsp;
                    <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="nr-editor-login__link"><?php echo esc_html__('Log out', 'woocommerce-product-reviews'); ?></a>
                </p>
            </div>
            <?php
            return ob_get_clean();
        }

        $error = isset($_GET['nr_login_error']) ? sanitize_text_field($_GET['nr_login_error']) : '';
        $messages = [
            'nonce'     => __('Security check failed. Please try again.', 'woocommerce-product-reviews'),
            'empty'     => __('Enter login and password.', 'woocommerce-product-reviews'),
            'invalid'   => __('Invalid login or password. Use the exact WordPress username from user profile.', 'woocommerce-product-reviews'),
            'forbidden' => __('This user has no permission to edit editor notes.', 'woocommerce-product-reviews'),
            'blocked'   => __('Too many failed attempts. Try again in one hour.', 'woocommerce-product-reviews'),
        ];
        $message = isset($messages[$error]) ? $messages[$error] : '';

        $redirect_to = $atts['redirect'] ? esc_url($atts['redirect']) : (NR_Core::instance()->get_option('editor_login_redirect') ?: '');
        if (!$redirect_to) {
            $redirect_to = home_url('/');
        }

        ob_start();
        ?>
        <div class="nr-editor-login">
            <h3 class="nr-editor-login__title"><?php echo esc_html__('Editor note login', 'woocommerce-product-reviews'); ?></h3>
            <?php if ($message) : ?>
                <p class="nr-editor-login__error"><?php echo esc_html($message); ?><?php if ($error === 'blocked') : ?> <?php echo esc_html__('Administrator can reset the lock in plugin settings.', 'woocommerce-product-reviews'); ?><?php endif; ?></p>
            <?php endif; ?>
            <form method="post" action="" class="nr-editor-login__form" id="nr-editor-login-form">
                <?php wp_nonce_field('nr_editor_login', 'nr_editor_nonce'); ?>
                <input type="hidden" name="nr_editor_login" value="1" />
                <input type="hidden" name="nr_editor_redirect" value="<?php echo esc_attr($redirect_to); ?>" />
                <p>
                    <label for="nr_editor_user"><?php echo esc_html__('Username', 'woocommerce-product-reviews'); ?></label><br>
                    <input type="text" name="nr_editor_user" id="nr_editor_user" class="nr-editor-login__input" required autocomplete="username" />
                </p>
                <p>
                    <label for="nr_editor_pass"><?php echo esc_html__('Password', 'woocommerce-product-reviews'); ?></label><br>
                    <input type="password" name="nr_editor_pass" id="nr_editor_pass" class="nr-editor-login__input" required autocomplete="current-password" />
                </p>
                <p>
                    <label><input type="checkbox" name="nr_editor_remember" value="1" /> <?php echo esc_html__('Remember me', 'woocommerce-product-reviews'); ?></label>
                </p>
                <p><button type="submit" class="nr-editor-login__submit"><?php echo esc_html__('Log in', 'woocommerce-product-reviews'); ?></button></p>
                <p class="nr-editor-login__message" style="display:none;"></p>
            </form>
            <script>
            (function(){
                var form = document.getElementById('nr-editor-login-form');
                if (!form) return;
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var btn = form.querySelector('.nr-editor-login__submit');
                    var msg = form.querySelector('.nr-editor-login__message');
                    btn.disabled = true;
                    msg.style.display = 'none';
                    var fd = new FormData();
                    fd.append('action', 'nr_editor_ajax_login');
                    fd.append('nonce', form.querySelector('[name="nr_editor_nonce"]').value);
                    fd.append('user', form.querySelector('[name="nr_editor_user"]').value);
                    fd.append('pass', form.querySelector('[name="nr_editor_pass"]').value);
                    fd.append('remember', form.querySelector('[name="nr_editor_remember"]') && form.querySelector('[name="nr_editor_remember"]').checked ? '1' : '');
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {method:'POST', body:fd, credentials:'same-origin'})
                        .then(function(r){return r.json();})
                        .then(function(r){
                            if (r.success) {
                                msg.style.color = 'green';
                                msg.textContent = r.data.message;
                                msg.style.display = '';
                                setTimeout(function(){window.location.href = r.data.redirect || '<?php echo esc_url($redirect_to); ?>';}, 500);
                            } else {
                                msg.style.color = '#c00';
                                msg.textContent = r.data && r.data.message ? r.data.message : 'Error';
                                msg.style.display = '';
                                btn.disabled = false;
                            }
                        })
                        .catch(function(){
                            msg.style.color = '#c00';
                            msg.textContent = 'Network error';
                            msg.style.display = '';
                            btn.disabled = false;
                        });
                });
            })();
            </script>
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
     * Unified shortcode for popular/latest/rated reviews.
     *
     * Attributes: count, title, type (comments|notes), orderby (popular|latest|rating),
     * order (ASC|DESC), product_id, template (compact|full), show_author (1|0),
     * show_product (1|0), show_rating (1|0), cache_ttl (seconds, 0=off).
     */
    public function popular_comments($atts) {
        return $this->render_comments_widget($atts, 'nr_popular_comments', 'popular');
    }

    public function latest_comments($atts) {
        return $this->render_comments_widget($atts, 'nr_latest_comments', 'latest');
    }

    private function render_comments_widget($atts, $shortcode, $default_orderby) {
        $defaults = [
            'count'        => 5,
            'title'        => $default_orderby === 'popular'
                ? __('Popular reviews', 'woocommerce-product-reviews')
                : __('Latest reviews', 'woocommerce-product-reviews'),
            'type'         => 'comments',
            'orderby'      => $default_orderby,  // popular|latest|rating
            'order'        => 'DESC',
            'product_id'   => 0,
            'template'     => 'compact',          // compact|full
            'show_author'  => 1,
            'show_product' => 1,
            'show_rating'  => 0,
            'cache_ttl'    => 300,                 // 5 min default, 0 = off
        ];
        $atts = shortcode_atts($defaults, $atts, $shortcode);

        if ($atts['type'] === 'notes') {
            return $this->latest_editor_notes([
                'count' => $atts['count'],
                'title' => $atts['title'] ?: __('Editor notes', 'woocommerce-product-reviews'),
            ]);
        }

        $limit      = max(1, min(50, (int) $atts['count']));
        $orderby    = in_array($atts['orderby'], ['popular', 'latest', 'rating'], true) ? $atts['orderby'] : $default_orderby;
        $order      = strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC';
        $product_id = (int) $atts['product_id'];
        $cache_ttl  = max(0, (int) $atts['cache_ttl']);

        // Versioned cache key — bumped on any comment lifecycle event
        $cache_ver = class_exists('NR_Rating') ? NR_Rating::get_widget_cache_version() : 0;
        $cache_key = 'nr_w_' . md5($shortcode . $orderby . $order . $limit . $product_id . $cache_ver);
        if ($cache_ttl > 0) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $comments = self::query_widget_comments($orderby, $order, $limit, $product_id);

        ob_start();
        $css_class = $orderby === 'popular' ? 'nr-popular-comments' : 'nr-latest-comments';
        echo '<div class="nr-widget ' . esc_attr($css_class) . '">';
        if ($atts['title']) {
            echo '<h3 class="nr-widget-title">' . esc_html($atts['title']) . '</h3>';
        }
        echo '<ul class="nr-comment-list">';
        foreach ($comments as $c) {
            $link = get_permalink($c->comment_post_ID);
            echo '<li class="nr-widget-item">';
            if (!empty($atts['show_rating']) && class_exists('NR_Rating')) {
                $r = (int) get_comment_meta($c->comment_ID, 'rating', true);
                if ($r > 0) echo NR_Rating::get_rating_html($r);
            }
            echo '<a href="' . esc_url($link) . '#comment-' . esc_attr($c->comment_ID) . '">';
            if ($atts['template'] === 'full') {
                echo esc_html($c->comment_content);
            } else {
                echo esc_html(wp_trim_words($c->comment_content, 15));
            }
            echo '</a>';
            $meta_parts = [];
            if (!empty($atts['show_author'])) {
                $meta_parts[] = esc_html($c->comment_author);
            }
            if (!empty($atts['show_product'])) {
                $meta_parts[] = esc_html(get_the_title($c->comment_post_ID));
            }
            if (!empty($meta_parts)) {
                echo ' <span class="nr-meta">' . implode(' · ', $meta_parts) . '</span>';
            }
            echo '</li>';
        }
        echo '</ul></div>';
        $html = ob_get_clean();

        if ($cache_ttl > 0) {
            set_transient($cache_key, $html, $cache_ttl);
        }

        return $html;
    }

    /**
     * Unified query for widget comments.
     */
    private static function query_widget_comments($orderby, $order, $limit, $product_id = 0) {
        global $wpdb;
        $where_product = $product_id > 0
            ? $wpdb->prepare(' AND c.comment_post_ID = %d', $product_id)
            : '';

        if ($orderby === 'popular') {
            $sql = "SELECT c.*, COUNT(r.comment_ID) AS reply_count
                    FROM {$wpdb->comments} c
                    INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID AND p.post_type = 'product'
                    LEFT JOIN {$wpdb->comments} r ON r.comment_parent = c.comment_ID AND r.comment_approved = '1'
                    WHERE c.comment_approved = '1' AND c.comment_parent = 0{$where_product}
                    GROUP BY c.comment_ID
                    ORDER BY reply_count {$order}, c.comment_date_gmt DESC
                    LIMIT %d";
        } elseif ($orderby === 'rating') {
            $sql = "SELECT c.*, CAST(cm.meta_value AS UNSIGNED) AS rating_val
                    FROM {$wpdb->comments} c
                    INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID AND p.post_type = 'product'
                    LEFT JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID AND cm.meta_key = 'rating'
                    WHERE c.comment_approved = '1'{$where_product}
                    ORDER BY rating_val {$order}, c.comment_date_gmt DESC
                    LIMIT %d";
        } else {
            $sql = "SELECT c.*
                    FROM {$wpdb->comments} c
                    INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
                       AND p.post_type = 'product' AND p.post_status = 'publish'
                    WHERE c.comment_approved = '1'{$where_product}
                    ORDER BY c.comment_date_gmt {$order}
                    LIMIT %d";
        }

        return $wpdb->get_results($wpdb->prepare($sql, $limit)) ?: [];
    }

    /**
     * Шорткод: последние опубликованные примечания редактора из товаров.
     * [nr_latest_editor_notes count="5" title="Примечания редактора"]
     */
    public function latest_editor_notes($atts) {
        $atts = shortcode_atts([
            'count' => 5,
            'title' => __('Editor notes', 'woocommerce-product-reviews'),
        ], $atts, 'nr_latest_editor_notes');

        $products = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, (int) $atts['count']),
            'meta_query'     => [
                [
                    'key'     => '_nr_editor_note',
                    'value'   => '',
                    'compare' => '!=',
                ],
            ],
            'orderby' => 'modified',
            'order'   => 'DESC',
        ]);

        ob_start();
        echo '<div class="nr-widget nr-latest-editor-notes">';
        echo '<h3 class="nr-widget-title">' . esc_html($atts['title']) . '</h3>';
        echo '<ul class="nr-comment-list">';
        foreach ($products as $p) {
            $note = get_post_meta($p->ID, '_nr_editor_note', true);
            $author = get_post_meta($p->ID, '_nr_editor_note_author', true);
            $link = get_permalink($p->ID);
            if (!is_string($note) || trim(wp_strip_all_tags($note)) === '') {
                continue;
            }
            echo '<li class="nr-widget-item">';
            echo ' <a href="' . esc_url($link) . '#nr-editor-note">' . esc_html(wp_trim_words(wp_strip_all_tags($note), 18)) . '</a>';
            echo ' <span class="nr-meta">' . esc_html($author ?: __('Editor', 'woocommerce-product-reviews')) . ' · ' . esc_html(get_the_title($p->ID)) . '</span>';
            echo '</li>';
        }
        echo '</ul></div>';
        return ob_get_clean();
    }
}
