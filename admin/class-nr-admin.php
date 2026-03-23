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
        add_action('admin_bar_menu', [$this, 'admin_bar'], 80);
    }

    /**
     * Admin bar: quick link to reviews settings + pending comments.
     */
    public function admin_bar($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        $pending = wp_count_comments();
        $count = $pending->moderated ?? 0;
        $title = 'Отзывы';
        if ($count > 0) {
            $title .= ' <span class="ab-label" style="background:#d63638;color:#fff;border-radius:10px;padding:0 6px;font-size:11px;margin-left:4px;">' . $count . '</span>';
        }
        $wp_admin_bar->add_node([
            'id'    => 'nr-reviews',
            'title' => $title,
            'href'  => admin_url('admin.php?page=woocommerce-product-reviews'),
        ]);
        $wp_admin_bar->add_node([
            'parent' => 'nr-reviews',
            'id'     => 'nr-reviews-settings',
            'title'  => 'Настройки',
            'href'   => admin_url('admin.php?page=woocommerce-product-reviews'),
        ]);
        $wp_admin_bar->add_node([
            'parent' => 'nr-reviews',
            'id'     => 'nr-reviews-comments',
            'title'  => 'Все комментарии',
            'href'   => admin_url('edit-comments.php'),
        ]);
    }

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
            'vk_secret'      => sanitize_text_field($_POST['nr_vk_secret'] ?? ''),
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
        echo '<div class="notice notice-success"><p>Настройки сохранены.</p></div>';
    }

    public function page() {
        $o = NR_Core::instance()->get_options();
        $callback = home_url('/nr-auth/');
        ?>
        <div class="wrap">
            <h1>WC Отзывы — Настройки</h1>

            <form method="post">
                <?php wp_nonce_field('nr_options', '_wpnonce'); ?>

                <!-- ═══ Social login ═══ -->
                <h2>Вход через соцсети</h2>
                <div class="notice notice-info inline" style="margin:10px 0 16px;padding:10px 14px;">
                    <p><strong>Перед настройкой:</strong></p>
                    <ol style="margin:4px 0 0 18px;">
                        <li>Настройки &rarr; Общие &rarr; поставьте галочку «Любой может зарегистрироваться».</li>
                        <li>Создайте приложение на сайте провайдера (ссылки ниже).</li>
                        <li>Скопируйте Redirect URI из инструкции ниже и вставьте в настройки приложения.</li>
                        <li>Скопируйте ключи из приложения и вставьте в поля ниже.</li>
                    </ol>
                </div>

                <table class="form-table">
                    <!-- VK ID -->
                    <tr>
                        <th>VK ID</th>
                        <td>
                            <label><input type="checkbox" name="nr_enable_vk" value="1" <?php checked(!empty($o['enable_vk'])); ?> /> Включить вход через VK</label><br>
                            <input type="text" name="nr_vk_app_id" value="<?php echo esc_attr($o['vk_app_id'] ?? ''); ?>" class="regular-text" placeholder="App ID (ID приложения)" /><br>
                            <input type="text" name="nr_vk_secret" value="<?php echo esc_attr($o['vk_secret'] ?? ''); ?>" class="regular-text" placeholder="Protected Key (Защищённый ключ)" />
                            <p class="description">
                                1. Откройте <a href="https://id.vk.com/about/business/go" target="_blank">VK ID для бизнеса</a> &rarr; создайте приложение (тип: Веб-сайт).<br>
                                2. Платформа: Веб. Укажите домен: <code><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></code><br>
                                3. В «Redirect URI» вставьте: <code><?php echo esc_html($callback); ?>vk/</code><br>
                                4. Скопируйте «App ID» &rarr; первое поле выше.<br>
                                5. Ключи доступа &rarr; «Защищённый ключ» &rarr; второе поле выше.<br>
                                6. Авторизация &rarr; Данные для регистрации &rarr; <strong>включите «Почта»</strong>.
                            </p>
                        </td>
                    </tr>

                    <!-- Одноклассники -->
                    <tr>
                        <th>Одноклассники (OK)</th>
                        <td>
                            <label><input type="checkbox" name="nr_enable_ok" value="1" <?php checked(!empty($o['enable_ok'])); ?> /> Включить вход через OK</label><br>
                            <input type="text" name="nr_ok_app_id" value="<?php echo esc_attr($o['ok_app_id'] ?? ''); ?>" class="regular-text" placeholder="Application ID" /><br>
                            <input type="text" name="nr_ok_app_key" value="<?php echo esc_attr($o['ok_app_key'] ?? ''); ?>" class="regular-text" placeholder="Application Key (Публичный ключ)" /><br>
                            <input type="text" name="nr_ok_secret" value="<?php echo esc_attr($o['ok_secret'] ?? ''); ?>" class="regular-text" placeholder="Application Secret (Секретный ключ)" />
                            <p class="description">
                                1. Откройте <a href="https://ok.ru/vitrine/myuploaded" target="_blank">OK &rarr; портал разработчика</a> &rarr; добавьте приложение (тип: Внешнее).<br>
                                2. В «Redirect URI» вставьте: <code><?php echo esc_html($callback); ?>ok/</code><br>
                                3. Скопируйте 3 ключа: Application ID, Application Key, Application Secret &rarr; поля выше.
                            </p>
                        </td>
                    </tr>

                    <!-- Яндекс -->
                    <tr>
                        <th>Яндекс</th>
                        <td>
                            <label><input type="checkbox" name="nr_enable_yandex" value="1" <?php checked(!empty($o['enable_yandex'])); ?> /> Включить вход через Яндекс</label><br>
                            <input type="text" name="nr_yandex_id" value="<?php echo esc_attr($o['yandex_id'] ?? ''); ?>" class="regular-text" placeholder="ClientID (ID приложения)" /><br>
                            <input type="text" name="nr_yandex_secret" value="<?php echo esc_attr($o['yandex_secret'] ?? ''); ?>" class="regular-text" placeholder="Client secret (Пароль приложения)" />
                            <p class="description">
                                1. Откройте <a href="https://oauth.yandex.ru/client/new" target="_blank">Яндекс OAuth &rarr; Создать приложение</a>.<br>
                                2. Платформа: Веб-сервисы. В «Callback URI» вставьте: <code><?php echo esc_html($callback); ?>yandex/</code><br>
                                3. Доступ: «Логин: адрес электронной почты» и «Логин: информация о пользователе».<br>
                                4. Скопируйте «ClientID» и «Client secret» &rarr; поля выше.
                            </p>
                        </td>
                    </tr>

                    <!-- Google -->
                    <tr>
                        <th>Google</th>
                        <td>
                            <label><input type="checkbox" name="nr_enable_google" value="1" <?php checked(!empty($o['enable_google'])); ?> /> Включить вход через Google</label><br>
                            <input type="text" name="nr_google_id" value="<?php echo esc_attr($o['google_id'] ?? ''); ?>" class="regular-text" placeholder="Client ID" /><br>
                            <input type="text" name="nr_google_secret" value="<?php echo esc_attr($o['google_secret'] ?? ''); ?>" class="regular-text" placeholder="Client Secret" />
                            <p class="description">
                                1. Откройте <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console &rarr; Credentials</a>.<br>
                                2. Создайте OAuth 2.0 Client ID (тип: Web application).<br>
                                3. В «Authorized redirect URIs» добавьте: <code><?php echo esc_html($callback); ?>google/</code><br>
                                4. Скопируйте «Client ID» и «Client secret» &rarr; поля выше.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- ═══ Reviews ═══ -->
                <h2>Отзывы</h2>
                <table class="form-table">
                    <tr>
                        <th>Заголовок примечания</th>
                        <td>
                            <input type="text" name="nr_editor_note_title" value="<?php echo esc_attr($o['editor_note_title'] ?? ''); ?>" class="regular-text" placeholder="Примечание редактора" />
                            <p class="description">Можно изменить на «Рецензия», «О книге» и т.д.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Редактор</th>
                        <td>
                            <label><input type="checkbox" name="nr_editor_smilies" value="1" <?php checked(!empty($o['editor_smilies'])); ?> /> Смайлики в редакторе примечаний</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Отзывов на страницу</th>
                        <td>
                            <input type="number" name="nr_comments_per_page" value="<?php echo esc_attr($o['comments_per_page'] ?? 10); ?>" min="5" max="50" />
                        </td>
                    </tr>
                    <tr>
                        <th>Ответы</th>
                        <td>
                            <label><input type="checkbox" name="nr_thread_depth" value="1" <?php checked(!empty($o['thread_depth'])); ?> /> Разрешить один уровень ответов</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Лимит</th>
                        <td>
                            <input type="number" name="nr_rate_limit_count" value="<?php echo esc_attr($o['rate_limit_count'] ?? 5); ?>" min="1" max="100" style="width:60px" />
                            отзывов за
                            <input type="number" name="nr_rate_limit_period" value="<?php echo esc_attr(($o['rate_limit_period'] ?? 3600) / 60); ?>" min="1" max="1440" style="width:60px" />
                            минут на IP
                        </td>
                    </tr>
                </table>

                <!-- ═══ Editor login ═══ -->
                <h2>Вход редактора</h2>
                <p class="description">Создайте отдельного WP-пользователя с ролью «Редактор» и передайте логин/пароль редактору.</p>
                <table class="form-table">
                    <tr>
                        <th>Страница входа</th>
                        <td>
                            <p>Создайте страницу с шорткодом <code>[nr_editor_login]</code> и отправьте URL редактору.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Редирект после входа</th>
                        <td>
                            <input type="url" name="nr_editor_login_redirect" value="<?php echo esc_attr($o['editor_login_redirect'] ?? ''); ?>" class="regular-text" placeholder="<?php echo esc_attr(home_url('/')); ?>" />
                            <p class="description">Оставьте пустым для перехода на главную.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Блокировка</th>
                        <td>
                            <p class="description">После 3 неудачных попыток IP блокируется на 1 час.</p>
                            <p><a href="<?php echo esc_url(wp_nonce_url(add_query_arg('nr_clear_login_blocks', '1', admin_url('admin.php?page=woocommerce-product-reviews')), 'nr_clear_login_blocks')); ?>" class="button">Сбросить блокировку</a></p>
                            <?php if (!empty($_GET['nr_blocks_cleared'])) : ?>
                                <p class="description" style="color:green;">Блокировка сброшена.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <!-- ═══ Shortcodes ═══ -->
                <h2>Шорткоды</h2>
                <ul style="list-style:disc; margin-left:20px;">
                    <li><strong>Страница входа редактора:</strong> <code>[nr_editor_login]</code></li>
                    <li><strong>Блок отзывов на странице товара:</strong> <code>[nr_product_reviews]</code> / <code>[nr_editor_note]</code></li>
                    <li><code>[nr_latest_comments count="5" title="Последние отзывы"]</code></li>
                    <li><code>[nr_popular_comments count="5" title="Популярные отзывы"]</code></li>
                    <li><code>[nr_latest_editor_notes count="5" title="Примечания редактора"]</code></li>
                </ul>

                <p class="submit">
                    <input type="submit" name="nr_save" class="button button-primary" value="Сохранить" />
                </p>
            </form>
        </div>
        <?php
    }
}
