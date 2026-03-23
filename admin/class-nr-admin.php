<?php
if (!defined('ABSPATH')) {
    exit;
}

class NR_Admin {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'save']);
        add_action('admin_init', [$this, 'maybe_clear_login_blocks']);
    }

    /**
     * Обработка нажатия «Сбросить блокировку входа редактора».
     */
    public function maybe_clear_login_blocks() {
        if (empty($_GET['nr_clear_login_blocks']) || !current_user_can('manage_options')) {
            return;
        }
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'nr_clear_login_blocks')) {
            return;
        }
        NR_Shortcodes::clear_login_blocks();
        wp_safe_redirect(add_query_arg(['nr_blocks_cleared' => '1'], remove_query_arg(['nr_clear_login_blocks', '_wpnonce'], wp_get_referer() ?: admin_url('admin.php?page=woocommerce-product-reviews'))));
        exit;
    }

    public function menu() {
        add_menu_page(
            'WooCommerce Product Reviews',
            'WC Отзывы',
            'manage_options',
            'woocommerce-product-reviews',
            [$this, 'page'],
            'dashicons-star-filled',
            26
        );
    }

    public function save() {
        if (!isset($_POST['nr_save']) || !current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'nr_options')) {
            return;
        }
        $opts = [
            'vk_app_id'      => sanitize_text_field($_POST['nr_vk_app_id'] ?? ''),
            'yandex_id'      => sanitize_text_field($_POST['nr_yandex_id'] ?? ''),
            'yandex_secret'  => sanitize_text_field($_POST['nr_yandex_secret'] ?? ''),
            'enable_vk'      => !empty($_POST['nr_enable_vk']) ? 1 : 0,
            'enable_yandex'  => !empty($_POST['nr_enable_yandex']) ? 1 : 0,
            'enable_ok'      => !empty($_POST['nr_enable_ok']) ? 1 : 0,
            'ok_app_id'      => sanitize_text_field($_POST['nr_ok_app_id'] ?? ''),
            'ok_app_key'     => sanitize_text_field($_POST['nr_ok_app_key'] ?? ''),
            'ok_secret'      => sanitize_text_field($_POST['nr_ok_secret'] ?? ''),
            'enable_google'  => !empty($_POST['nr_enable_google']) ? 1 : 0,
            'google_id'      => sanitize_text_field($_POST['nr_google_id'] ?? ''),
            'google_secret'  => sanitize_text_field($_POST['nr_google_secret'] ?? ''),
            'thread_depth'   => max(0, min(1, (int) ($_POST['nr_thread_depth'] ?? 1))),
            'rate_limit_count' => max(1, min(100, (int) ($_POST['nr_rate_limit_count'] ?? 5))),
            'rate_limit_period' => max(60, min(86400, (int) ($_POST['nr_rate_limit_period'] ?? 60) * 60)),
            'editor_smilies' => !empty($_POST['nr_editor_smilies']) ? 1 : 0,
            'comments_per_page' => max(5, min(50, (int) ($_POST['nr_comments_per_page'] ?? 10))),
            'editor_login_redirect' => esc_url_raw($_POST['nr_editor_login_redirect'] ?? ''),
            'editor_login_page_id' => (int) NR_Core::instance()->get_option('editor_login_page_id', 0),
            'editor_note_title' => sanitize_text_field($_POST['nr_editor_note_title'] ?? ''),
        ];
        update_option('nr_options', $opts);
        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'woocommerce-product-reviews') . '</p></div>';
    }

    public function page() {
        $o = NR_Core::instance()->get_options();
        $callback = home_url('/nr-auth/');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WooCommerce Product Reviews - settings', 'woocommerce-product-reviews'); ?></h1>

            <form method="post">
                <?php wp_nonce_field('nr_options', '_wpnonce'); ?>

                <h2><?php echo esc_html__('Social login (VK, OK, Yandex, Google)', 'woocommerce-product-reviews'); ?></h2>
                <div class="notice notice-info inline" style="margin:10px 0 16px;padding:10px 14px;">
                    <p><strong><?php echo esc_html__('Before you start:', 'woocommerce-product-reviews'); ?></strong></p>
                    <ol style="margin:4px 0 0 18px;">
                        <li><?php echo esc_html__('Go to Settings -> General -> check "Anyone can register".', 'woocommerce-product-reviews'); ?></li>
                        <li><?php echo esc_html__('Create an app on the provider site (links below).', 'woocommerce-product-reviews'); ?></li>
                        <li><?php echo esc_html__('Copy the Redirect URI shown below and paste it into the app settings.', 'woocommerce-product-reviews'); ?></li>
                        <li><?php echo esc_html__('Copy the app ID/secret from the provider and paste them here.', 'woocommerce-product-reviews'); ?></li>
                    </ol>
                </div>

                <table class="form-table">
                    <tr>
                        <th>VK</th>
                        <td>
                            <label><input type="checkbox" name="nr_enable_vk" value="1" <?php checked(!empty($o['enable_vk'])); ?> /> <?php echo esc_html__('Enable VK login', 'woocommerce-product-reviews'); ?></label><br>
                            <input type="text" name="nr_vk_app_id" value="<?php echo esc_attr($o['vk_app_id'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr__('VK application ID', 'woocommerce-product-reviews'); ?>" />
                            <p class="description">
                                <?php echo esc_html__('1. Go to', 'woocommerce-product-reviews'); ?> <a href="https://id.vk.com/about/business/go" target="_blank">VK ID &rarr; <?php echo esc_html__('Create app', 'woocommerce-product-reviews'); ?></a><br>
                                <?php echo esc_html__('2. App type: Web. Platform: Web.', 'woocommerce-product-reviews'); ?><br>
                                <?php echo esc_html__('3. In "Redirect URI" paste:', 'woocommerce-product-reviews'); ?> <code><?php echo esc_html($callback); ?>vk</code><br>
                                <?php echo esc_html__('4. Copy "App ID" and paste above.', 'woocommerce-product-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Odnoklassniki (OK)', 'woocommerce-product-reviews'); ?></th>
                        <td>
                            <label><input type="checkbox" name="nr_enable_ok" value="1" <?php checked(!empty($o['enable_ok'])); ?> /> <?php echo esc_html__('Enable OK login', 'woocommerce-product-reviews'); ?></label><br>
                            <input type="text" name="nr_ok_app_id" value="<?php echo esc_attr($o['ok_app_id'] ?? ''); ?>" class="regular-text" placeholder="Application ID" /><br>
                            <input type="text" name="nr_ok_app_key" value="<?php echo esc_attr($o['ok_app_key'] ?? ''); ?>" class="regular-text" placeholder="Application Key" /><br>
                            <input type="text" name="nr_ok_secret" value="<?php echo esc_attr($o['ok_secret'] ?? ''); ?>" class="regular-text" placeholder="Application Secret" />
                            <p class="description">
                                <?php echo esc_html__('1. Go to', 'woocommerce-product-reviews'); ?> <a href="https://ok.ru/vitrine/myuploaded" target="_blank">OK <?php echo esc_html__('developer portal', 'woocommerce-product-reviews'); ?></a> &rarr; <?php echo esc_html__('add app (type: External).', 'woocommerce-product-reviews'); ?><br>
                                <?php echo esc_html__('2. In "Redirect URI" paste:', 'woocommerce-product-reviews'); ?> <code><?php echo esc_html($callback); ?>ok</code><br>
                                <?php echo esc_html__('3. You will get 3 keys: Application ID, Application Key, Application Secret — paste all three above.', 'woocommerce-product-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Yandex</th>
                        <td>
                            <label><input type="checkbox" name="nr_enable_yandex" value="1" <?php checked(!empty($o['enable_yandex'])); ?> /> <?php echo esc_html__('Enable Yandex login', 'woocommerce-product-reviews'); ?></label><br>
                            <input type="text" name="nr_yandex_id" value="<?php echo esc_attr($o['yandex_id'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr__('Application ID', 'woocommerce-product-reviews'); ?>" /><br>
                            <input type="text" name="nr_yandex_secret" value="<?php echo esc_attr($o['yandex_secret'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr__('Application secret', 'woocommerce-product-reviews'); ?>" />
                            <p class="description">
                                <?php echo esc_html__('1. Go to', 'woocommerce-product-reviews'); ?> <a href="https://oauth.yandex.ru/client/new" target="_blank">Yandex OAuth &rarr; <?php echo esc_html__('Create app', 'woocommerce-product-reviews'); ?></a><br>
                                <?php echo esc_html__('2. Platform: Web services. In "Callback URI" paste:', 'woocommerce-product-reviews'); ?> <code><?php echo esc_html($callback); ?>yandex</code><br>
                                <?php echo esc_html__('3. Access: check "Login: email address" and "Login: user info".', 'woocommerce-product-reviews'); ?><br>
                                <?php echo esc_html__('4. Copy "ClientID" and "Client secret" — paste above.', 'woocommerce-product-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Google</th>
                        <td>
                            <label><input type="checkbox" name="nr_enable_google" value="1" <?php checked(!empty($o['enable_google'])); ?> /> <?php echo esc_html__('Enable Google login', 'woocommerce-product-reviews'); ?></label><br>
                            <input type="text" name="nr_google_id" value="<?php echo esc_attr($o['google_id'] ?? ''); ?>" class="regular-text" placeholder="Client ID" /><br>
                            <input type="text" name="nr_google_secret" value="<?php echo esc_attr($o['google_secret'] ?? ''); ?>" class="regular-text" placeholder="Client Secret" />
                            <p class="description">
                                <?php echo esc_html__('1. Go to', 'woocommerce-product-reviews'); ?> <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console &rarr; Credentials</a><br>
                                <?php echo esc_html__('2. Create OAuth 2.0 Client ID (type: Web application).', 'woocommerce-product-reviews'); ?><br>
                                <?php echo esc_html__('3. In "Authorized redirect URIs" add:', 'woocommerce-product-reviews'); ?> <code><?php echo esc_html($callback); ?>google</code><br>
                                <?php echo esc_html__('4. Copy "Client ID" and "Client secret" — paste above.', 'woocommerce-product-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Reviews', 'woocommerce-product-reviews'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Editor note title', 'woocommerce-product-reviews'); ?></th>
                        <td>
                            <input type="text" name="nr_editor_note_title" value="<?php echo esc_attr($o['editor_note_title'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr__('Примечание редактора', 'woocommerce-product-reviews'); ?>" />
                            <p class="description"><?php echo esc_html__('Можно изменить на «Рецензия», «О книге» и т.д. По умолчанию: Примечание редактора', 'woocommerce-product-reviews'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Editor', 'woocommerce-product-reviews'); ?></th>
                        <td>
                            <label><input type="checkbox" name="nr_editor_smilies" value="1" <?php checked(!empty($o['editor_smilies'])); ?> /> <?php echo esc_html__('Smilies in editor', 'woocommerce-product-reviews'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Reviews per page', 'woocommerce-product-reviews'); ?></th>
                        <td>
                            <input type="number" name="nr_comments_per_page" value="<?php echo esc_attr($o['comments_per_page'] ?? 10); ?>" min="5" max="50" />
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Reply threads', 'woocommerce-product-reviews'); ?></th>
                        <td>
                            <label><input type="checkbox" name="nr_thread_depth" value="1" <?php checked(!empty($o['thread_depth'])); ?> /> <?php echo esc_html__('Enable one-level reply threads', 'woocommerce-product-reviews'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Rate limit', 'woocommerce-product-reviews'); ?></th>
                        <td>
                            <input type="number" name="nr_rate_limit_count" value="<?php echo esc_attr($o['rate_limit_count'] ?? 5); ?>" min="1" max="100" style="width:60px" />
                            <?php echo esc_html__('reviews per', 'woocommerce-product-reviews'); ?>
                            <input type="number" name="nr_rate_limit_period" value="<?php echo esc_attr(($o['rate_limit_period'] ?? 3600) / 60); ?>" min="1" max="1440" style="width:60px" />
                            <?php echo esc_html__('minutes per IP', 'woocommerce-product-reviews'); ?>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Editor note login', 'woocommerce-product-reviews'); ?></h2>
                <p class="description"><?php echo esc_html__('Create a separate WordPress user for editor notes and provide username/password to the editor.', 'woocommerce-product-reviews'); ?></p>
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Login page', 'woocommerce-product-reviews'); ?></th>
                        <td>
                            <p><?php echo esc_html__('Create a page with shortcode [nr_editor_login] and send this URL to editors.', 'woocommerce-product-reviews'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Redirect after login', 'woocommerce-product-reviews'); ?></th>
                        <td>
                            <input type="url" name="nr_editor_login_redirect" value="<?php echo esc_attr($o['editor_login_redirect'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr(home_url('/')); ?>" />
                            <p class="description"><?php echo esc_html__('Leave empty to redirect to homepage.', 'woocommerce-product-reviews'); ?></p>
                            <p class="description" style="margin-top:10px;"><strong><?php echo esc_html__('Important:', 'woocommerce-product-reviews'); ?></strong> <?php echo esc_html__('If page cache is enabled, disable cache for logged-in users or exclude product pages.', 'woocommerce-product-reviews'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Login lock', 'woocommerce-product-reviews'); ?></th>
                        <td>
                            <p class="description"><?php echo esc_html__('After 3 failed attempts the IP is blocked for 1 hour.', 'woocommerce-product-reviews'); ?></p>
                            <p><a href="<?php echo esc_url(wp_nonce_url(add_query_arg('nr_clear_login_blocks', '1', admin_url('admin.php?page=woocommerce-product-reviews')), 'nr_clear_login_blocks')); ?>" class="button"><?php echo esc_html__('Reset editor login lock', 'woocommerce-product-reviews'); ?></a></p>
                            <?php if (!empty($_GET['nr_blocks_cleared'])) : ?>
                                <p class="description" style="color:green;"><?php echo esc_html__('Lock reset successfully.', 'woocommerce-product-reviews'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Shortcodes', 'woocommerce-product-reviews'); ?></h2>
                <p><?php echo esc_html__('Insert into content or widget:', 'woocommerce-product-reviews'); ?></p>
                <ul style="list-style:disc; margin-left:20px;">
                    <li><strong><?php echo esc_html__('Editor login page:', 'woocommerce-product-reviews'); ?></strong> <code>[nr_editor_login]</code></li>
                    <li><strong><?php echo esc_html__('Product page block:', 'woocommerce-product-reviews'); ?></strong> <code>[nr_product_reviews]</code> / <code>[nr_editor_note]</code></li>
                    <li><code>[nr_latest_comments count="5" title="Latest reviews"]</code></li>
                    <li><code>[nr_popular_comments count="5" title="Popular reviews"]</code></li>
                    <li><code>[nr_latest_editor_notes count="5" title="Editor notes"]</code></li>
                </ul>

                <p class="submit">
                    <input type="submit" name="nr_save" class="button button-primary" value="<?php echo esc_attr__('Save', 'woocommerce-product-reviews'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }
}
