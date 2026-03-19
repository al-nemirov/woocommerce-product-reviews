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
            'editor_smilies'        => !empty($_POST['nr_editor_smilies']) ? 1 : 0,
            'comments_per_page'     => max(5, min(50, (int) ($_POST['nr_comments_per_page'] ?? 10))),
            'editor_login_redirect' => esc_url_raw($_POST['nr_editor_login_redirect'] ?? ''),
        ];
        update_option('nr_options', $opts);
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    public function page() {
        $o = spr_instance()->get_options();
        ?>
        <div class="wrap">
            <h1>Smart Product Reviews — Settings</h1>

            <form method="post">
                <?php wp_nonce_field('nr_options', '_wpnonce'); ?>

                <h2>Reviews</h2>
                <table class="form-table">
                    <tr>
                        <th>Editor</th>
                        <td>
                            <label><input type="checkbox" name="nr_editor_smilies" value="1" <?php checked(!empty($o['editor_smilies'])); ?> /> Enable emoji picker in review editor</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Reviews per page</th>
                        <td>
                            <input type="number" name="nr_comments_per_page" value="<?php echo esc_attr($o['comments_per_page'] ?? 10); ?>" min="5" max="50" />
                        </td>
                    </tr>
                </table>

                <h2>Editor Notes Login</h2>
                <p class="description">Create a separate WordPress user (Users &rarr; Add New): set a <strong>username</strong> and <strong>password</strong> for the editor. In the "Display Name" field, enter the name that will appear on notes. Role: <strong>Author</strong> or <strong>Editor</strong>.</p>
                <table class="form-table">
                    <tr>
                        <th>Login page</th>
                        <td>
                            <p>Create a page (e.g., "Editor Login"), insert the shortcode <code>[nr_editor_login]</code>. Share the link with your editor.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Redirect after login</th>
                        <td>
                            <input type="url" name="nr_editor_login_redirect" value="<?php echo esc_attr($o['editor_login_redirect'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr(home_url('/')); ?>" />
                            <p class="description">Leave empty to redirect to homepage.</p>
                            <p class="description" style="margin-top:10px;"><strong>Note:</strong> If page caching is enabled, make sure logged-in users see uncached pages, otherwise the "Edit Note" button won't appear.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Login blocking</th>
                        <td>
                            <p class="description">After 3 failed login attempts, the IP is blocked for 1 hour. To unblock the editor:</p>
                            <p><a href="<?php echo esc_url(wp_nonce_url(add_query_arg('nr_clear_login_blocks', '1', admin_url('admin.php?page=smart-product-reviews')), 'nr_clear_login_blocks')); ?>" class="button">Reset editor login blocks</a></p>
                            <?php if (!empty($_GET['nr_blocks_cleared'])) : ?>
                                <p class="description" style="color:green;">Blocks cleared. The editor can log in again.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2>Shortcodes</h2>
                <ul style="list-style:disc; margin-left:20px;">
                    <li><strong>Editor login:</strong> <code>[nr_editor_login]</code> — login form. Optional: <code>[nr_editor_login redirect="https://site.com/shop/"]</code></li>
                    <li><strong>Product page:</strong> <code>[nr_product_reviews]</code> — reviews block. <code>[nr_editor_note]</code> — editor note block.</li>
                    <li><code>[nr_latest_comments count="5" title="Latest Reviews"]</code> — latest reviews</li>
                    <li><code>[nr_popular_comments count="5" title="Popular Reviews"]</code> — popular reviews</li>
                </ul>

                <p class="submit">
                    <input type="submit" name="nr_save" class="button button-primary" value="Save Settings" />
                </p>
            </form>
        </div>
        <?php
    }
}
