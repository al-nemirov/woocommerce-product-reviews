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
        wp_safe_redirect(add_query_arg(['nr_blocks_cleared' => '1'], remove_query_arg(['nr_clear_login_blocks', '_wpnonce'], wp_get_referer() ?: admin_url('admin.php?page=smart-product-reviews'))));
        exit;
    }

    public function menu() {
        add_menu_page(
            'Smart Product Reviews',
            'Smart Product Reviews',
            'manage_options',
            'smart-product-reviews',
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
            'editor_smilies' => !empty($_POST['nr_editor_smilies']) ? 1 : 0,
            'comments_per_page' => max(5, min(50, (int) ($_POST['nr_comments_per_page'] ?? 10))),
            'editor_login_redirect' => esc_url_raw($_POST['nr_editor_login_redirect'] ?? ''),
        ];
        update_option('nr_options', $opts);
        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'smart-product-reviews') . '</p></div>';
    }

    public function page() {
        $o = NR_Core::instance()->get_options();
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Smart Product Reviews - settings', 'smart-product-reviews'); ?></h1>

            <form method="post">
                <?php wp_nonce_field('nr_options', '_wpnonce'); ?>

                <h2><?php echo esc_html__('Login (VK, Yandex, site profile)', 'smart-product-reviews'); ?></h2>
                <p class="description"><?php echo esc_html__('To enable social login, turn on "Anyone can register" in Settings -> General.', 'smart-product-reviews'); ?></p>

                <table class="form-table">
                    <tr>
                        <th>VK</th>
                        <td>
                            <label><input type="checkbox" name="nr_enable_vk" value="1" <?php checked(!empty($o['enable_vk'])); ?> /> <?php echo esc_html__('Enable VK login', 'smart-product-reviews'); ?></label><br>
                            <input type="text" name="nr_vk_app_id" value="<?php echo esc_attr($o['vk_app_id'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr__('VK application ID', 'smart-product-reviews'); ?>" />
                            <p class="description">Redirect URI: <code><?php echo esc_html($callback); ?>vk</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th>Yandex</th>
                        <td>
                            <label><input type="checkbox" name="nr_enable_yandex" value="1" <?php checked(!empty($o['enable_yandex'])); ?> /> <?php echo esc_html__('Enable Yandex login', 'smart-product-reviews'); ?></label><br>
                            <input type="text" name="nr_yandex_id" value="<?php echo esc_attr($o['yandex_id'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr__('Application ID', 'smart-product-reviews'); ?>" /><br>
                            <input type="text" name="nr_yandex_secret" value="<?php echo esc_attr($o['yandex_secret'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr__('Application secret', 'smart-product-reviews'); ?>" />
                            <p class="description">Callback: <code><?php echo esc_html($callback); ?>yandex</code></p>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Reviews', 'smart-product-reviews'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Editor', 'smart-product-reviews'); ?></th>
                        <td>
                            <label><input type="checkbox" name="nr_editor_smilies" value="1" <?php checked(!empty($o['editor_smilies'])); ?> /> <?php echo esc_html__('Smilies in editor', 'smart-product-reviews'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Reviews per page', 'smart-product-reviews'); ?></th>
                        <td>
                            <input type="number" name="nr_comments_per_page" value="<?php echo esc_attr($o['comments_per_page'] ?? 10); ?>" min="5" max="50" />
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Editor note login', 'smart-product-reviews'); ?></h2>
                <p class="description"><?php echo esc_html__('Create a separate WordPress user for editor notes and provide username/password to the editor.', 'smart-product-reviews'); ?></p>
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Login page', 'smart-product-reviews'); ?></th>
                        <td>
                            <p><?php echo esc_html__('Create a page with shortcode [nr_editor_login] and send this URL to editors.', 'smart-product-reviews'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Redirect after login', 'smart-product-reviews'); ?></th>
                        <td>
                            <input type="url" name="nr_editor_login_redirect" value="<?php echo esc_attr($o['editor_login_redirect'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr(home_url('/')); ?>" />
                            <p class="description"><?php echo esc_html__('Leave empty to redirect to homepage.', 'smart-product-reviews'); ?></p>
                            <p class="description" style="margin-top:10px;"><strong><?php echo esc_html__('Important:', 'smart-product-reviews'); ?></strong> <?php echo esc_html__('If page cache is enabled, disable cache for logged-in users or exclude product pages.', 'smart-product-reviews'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Login lock', 'smart-product-reviews'); ?></th>
                        <td>
                            <p class="description"><?php echo esc_html__('After 3 failed attempts the IP is blocked for 1 hour.', 'smart-product-reviews'); ?></p>
                            <p><a href="<?php echo esc_url(wp_nonce_url(add_query_arg('nr_clear_login_blocks', '1', admin_url('admin.php?page=smart-product-reviews')), 'nr_clear_login_blocks')); ?>" class="button"><?php echo esc_html__('Reset editor login lock', 'smart-product-reviews'); ?></a></p>
                            <?php if (!empty($_GET['nr_blocks_cleared'])) : ?>
                                <p class="description" style="color:green;"><?php echo esc_html__('Lock reset successfully.', 'smart-product-reviews'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Shortcodes', 'smart-product-reviews'); ?></h2>
                <p><?php echo esc_html__('Insert into content or widget:', 'smart-product-reviews'); ?></p>
                <ul style="list-style:disc; margin-left:20px;">
                    <li><strong><?php echo esc_html__('Editor login page:', 'smart-product-reviews'); ?></strong> <code>[nr_editor_login]</code></li>
                    <li><strong><?php echo esc_html__('Product page block:', 'smart-product-reviews'); ?></strong> <code>[nr_product_reviews]</code> / <code>[nr_editor_note]</code></li>
                    <li><code>[nr_latest_comments count="5" title="Latest reviews"]</code></li>
                    <li><code>[nr_popular_comments count="5" title="Popular reviews"]</code></li>
                    <li><code>[nr_latest_editor_notes count="5" title="Editor notes"]</code></li>
                </ul>

                <p class="submit">
                    <input type="submit" name="nr_save" class="button button-primary" value="<?php echo esc_attr__('Save', 'smart-product-reviews'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }
}
