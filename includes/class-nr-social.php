<?php
if (!defined('ABSPATH')) {
    exit;
}

class NR_Social {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('wp_ajax_nr_social_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_nr_social_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nr_social_callback', [$this, 'callback']);
        add_action('wp_ajax_nopriv_nr_social_callback', [$this, 'callback']);
    }

    /**
     * Returns list of enabled provider keys.
     */
    public static function get_enabled_providers() {
        $core = NR_Core::instance();
        $providers = [];
        if ($core->get_option('enable_vk'))     $providers[] = 'vk';
        if ($core->get_option('enable_ok'))     $providers[] = 'ok';
        if ($core->get_option('enable_yandex')) $providers[] = 'yandex';
        if ($core->get_option('enable_google')) $providers[] = 'google';
        return $providers;
    }

    /**
     * Render social login buttons HTML.
     */
    public static function render_buttons($post_id) {
        $providers = self::get_enabled_providers();
        if (empty($providers)) {
            return '';
        }
        $labels = [
            'vk'     => 'VK',
            'ok'     => 'OK',
            'yandex' => __('Yandex', 'smart-product-reviews'),
            'google' => 'Google',
        ];
        $html = '<div class="nr-social-login">';
        $html .= '<span class="nr-connect-label">' . esc_html__('Log in via:', 'smart-product-reviews') . '</span>';
        foreach ($providers as $p) {
            $html .= '<button type="button" class="nr-btn nr-' . esc_attr($p) . '" data-provider="' . esc_attr($p) . '" data-post-id="' . (int) $post_id . '">' . esc_html($labels[$p]) . '</button>';
        }
        $html .= '</div>';
        return $html;
    }

    public function ajax_login() {
        check_ajax_referer('nr_social_login', 'nonce');

        if (!get_option('users_can_register')) {
            wp_send_json_error(['message' => __('Registration is disabled.', 'smart-product-reviews')]);
        }
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $post_id  = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        // Verify provider is enabled in settings
        $enabled = self::get_enabled_providers();
        if (!in_array($provider, $enabled, true)) {
            wp_send_json_error(['message' => __('This login provider is not enabled.', 'smart-product-reviews')]);
        }

        $method = $provider . '_redirect';
        if (method_exists($this, $method)) {
            $this->$method($post_id);
        }
        wp_send_json_error(['message' => __('Unknown provider.', 'smart-product-reviews')]);
    }

    // ── VK (PKCE) ─────────────────────────────────────

    private function vk_redirect($post_id) {
        $app_id = NR_Core::instance()->get_option('vk_app_id');
        if (!$app_id) {
            wp_send_json_error(['message' => __('VK not configured.', 'smart-product-reviews')]);
        }
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=vk');
        $state = wp_create_nonce('nr_vk_' . $post_id);
        $verifier = bin2hex(random_bytes(32));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        set_transient('nr_oauth_' . md5($state), [
            'provider' => 'vk', 'post_id' => $post_id, 'code_verifier' => $verifier,
            'session_hash' => self::get_session_hash(),
        ], 600);
        $url = 'https://id.vk.ru/authorize?' . http_build_query([
            'client_id'            => $app_id,
            'redirect_uri'         => $callback,
            'response_type'        => 'code',
            'scope'                => 'openid email',
            'state'                => $state,
            'code_challenge'       => $challenge,
            'code_challenge_method'=> 'S256',
        ]);
        wp_send_json_success(['url' => $url]);
    }

    private function vk_get_user($code, $data) {
        $app_id = NR_Core::instance()->get_option('vk_app_id');
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=vk');
        $body = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $app_id,
            'code'          => $code,
            'redirect_uri'  => $callback,
        ];
        if (!empty($data['code_verifier'])) {
            $body['code_verifier'] = $data['code_verifier'];
        }
        $res = wp_remote_post('https://id.vk.ru/oauth2/auth', ['body' => $body]);
        if (is_wp_error($res)) return $res;
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($body['access_token'])) {
            return new WP_Error('nr_vk', __('VK: no access token', 'smart-product-reviews'));
        }
        $uid = isset($body['user_id']) ? $body['user_id'] : '';
        $user_res = wp_remote_get('https://id.vk.ru/userinfo?access_token=' . urlencode($body['access_token']));
        if (is_wp_error($user_res)) return $user_res;
        $user = json_decode(wp_remote_retrieve_body($user_res), true);
        $email = isset($user['email']) ? $user['email'] : ($uid ? $uid . '@vk.id' : '');
        $name = trim((isset($user['given_name']) ? $user['given_name'] : '') . ' ' . (isset($user['family_name']) ? $user['family_name'] : ''));
        if (!$name && isset($user['name'])) $name = $user['name'];
        if (!$name) $name = $uid ? 'VK_' . $uid : 'VK';
        if (!$email) $email = $uid ? $uid . '@vk.id' : uniqid('vk_') . '@temp.local';
        return $this->get_or_create_user($email, $name, 'vk', $uid);
    }

    // ── Одноклассники (OK) ────────────────────────────

    private function ok_redirect($post_id) {
        $app_id = NR_Core::instance()->get_option('ok_app_id');
        $secret = NR_Core::instance()->get_option('ok_secret');
        if (!$app_id || !$secret) {
            wp_send_json_error(['message' => __('OK not configured.', 'smart-product-reviews')]);
        }
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=ok');
        $state = wp_create_nonce('nr_ok_' . $post_id);
        set_transient('nr_oauth_' . md5($state), ['provider' => 'ok', 'post_id' => $post_id, 'session_hash' => self::get_session_hash()], 600);
        $url = 'https://connect.ok.ru/oauth/authorize?' . http_build_query([
            'client_id'     => $app_id,
            'redirect_uri'  => $callback,
            'response_type' => 'code',
            'scope'         => 'VALUABLE_ACCESS;GET_EMAIL',
            'state'         => $state,
        ]);
        wp_send_json_success(['url' => $url]);
    }

    private function ok_get_user($code, $data) {
        $core = NR_Core::instance();
        $app_id  = $core->get_option('ok_app_id');
        $app_key = $core->get_option('ok_app_key');
        $secret  = $core->get_option('ok_secret');
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=ok');

        $res = wp_remote_post('https://api.ok.ru/oauth/token.do', [
            'body' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $app_id,
                'client_secret' => $secret,
                'code'          => $code,
                'redirect_uri'  => $callback,
            ],
        ]);
        if (is_wp_error($res)) return $res;
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($body['access_token'])) {
            return new WP_Error('nr_ok', __('OK: no access token', 'smart-product-reviews'));
        }
        $token = $body['access_token'];

        // OK API requires sig = md5(params_sorted + md5(access_token + application_secret_key))
        $secret_key = md5($token . $secret);
        $params = [
            'application_key' => $app_key,
            'fields'          => 'uid,first_name,last_name,email',
            'format'          => 'json',
            'method'          => 'users.getCurrentUser',
        ];
        ksort($params);
        $sig_str = '';
        foreach ($params as $k => $v) {
            $sig_str .= $k . '=' . $v;
        }
        $sig = md5($sig_str . $secret_key);
        $params['access_token'] = $token;
        $params['sig'] = $sig;

        $user_res = wp_remote_get('https://api.ok.ru/fb.do?' . http_build_query($params));
        if (is_wp_error($user_res)) return $user_res;
        $user = json_decode(wp_remote_retrieve_body($user_res), true);
        if (isset($user['error_code'])) {
            return new WP_Error('nr_ok', __('OK API error', 'smart-product-reviews'));
        }
        $uid   = isset($user['uid']) ? $user['uid'] : '';
        $email = isset($user['email']) ? $user['email'] : ($uid ? $uid . '@ok.ru' : '');
        $name  = trim((isset($user['first_name']) ? $user['first_name'] : '') . ' ' . (isset($user['last_name']) ? $user['last_name'] : ''));
        if (!$name) $name = $uid ? 'OK_' . $uid : 'OK';
        if (!$email) $email = $uid ? $uid . '@ok.ru' : uniqid('ok_') . '@temp.local';
        return $this->get_or_create_user($email, $name, 'ok', $uid);
    }

    // ── Яндекс ────────────────────────────────────────

    private function yandex_redirect($post_id) {
        $id = NR_Core::instance()->get_option('yandex_id');
        $secret = NR_Core::instance()->get_option('yandex_secret');
        if (!$id || !$secret) {
            wp_send_json_error(['message' => __('Yandex not configured.', 'smart-product-reviews')]);
        }
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=yandex');
        $state = wp_create_nonce('nr_ya_' . $post_id);
        set_transient('nr_oauth_' . md5($state), ['provider' => 'yandex', 'post_id' => $post_id, 'session_hash' => self::get_session_hash()], 600);
        $url = 'https://oauth.yandex.ru/authorize?' . http_build_query([
            'client_id'     => $id,
            'redirect_uri'  => $callback,
            'response_type' => 'code',
            'state'         => $state,
        ]);
        wp_send_json_success(['url' => $url]);
    }

    private function yandex_get_user($code, $data) {
        $id = NR_Core::instance()->get_option('yandex_id');
        $secret = NR_Core::instance()->get_option('yandex_secret');
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=yandex');
        $res = wp_remote_post('https://oauth.yandex.ru/token', [
            'body' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $id,
                'client_secret' => $secret,
                'code'          => $code,
                'redirect_uri'  => $callback,
            ],
        ]);
        if (is_wp_error($res)) return $res;
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($body['access_token'])) {
            return new WP_Error('nr_ya', __('Yandex: no access token', 'smart-product-reviews'));
        }
        $user_res = wp_remote_get('https://login.yandex.ru/info?format=json', [
            'headers' => ['Authorization' => 'OAuth ' . $body['access_token']],
        ]);
        if (is_wp_error($user_res)) return $user_res;
        $user = json_decode(wp_remote_retrieve_body($user_res), true);
        $email = isset($user['default_email']) ? $user['default_email'] : (isset($user['id']) ? $user['id'] . '@yandex.ru' : '');
        $name = isset($user['real_name']) ? $user['real_name'] : (isset($user['login']) ? $user['login'] : 'Yandex');
        $uid = isset($user['id']) ? $user['id'] : '';
        return $this->get_or_create_user($email, $name, 'yandex', $uid);
    }

    // ── Google ─────────────────────────────────────────

    private function google_redirect($post_id) {
        $id = NR_Core::instance()->get_option('google_id');
        $secret = NR_Core::instance()->get_option('google_secret');
        if (!$id || !$secret) {
            wp_send_json_error(['message' => __('Google not configured.', 'smart-product-reviews')]);
        }
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=google');
        $state = wp_create_nonce('nr_gg_' . $post_id);
        set_transient('nr_oauth_' . md5($state), ['provider' => 'google', 'post_id' => $post_id, 'session_hash' => self::get_session_hash()], 600);
        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $id,
            'redirect_uri'  => $callback,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'offline',
            'prompt'        => 'select_account',
        ]);
        wp_send_json_success(['url' => $url]);
    }

    private function google_get_user($code, $data) {
        $id = NR_Core::instance()->get_option('google_id');
        $secret = NR_Core::instance()->get_option('google_secret');
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=google');
        $res = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $id,
                'client_secret' => $secret,
                'code'          => $code,
                'redirect_uri'  => $callback,
            ],
        ]);
        if (is_wp_error($res)) return $res;
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($body['access_token'])) {
            return new WP_Error('nr_gg', __('Google: no access token', 'smart-product-reviews'));
        }
        $user_res = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
            'headers' => ['Authorization' => 'Bearer ' . $body['access_token']],
        ]);
        if (is_wp_error($user_res)) return $user_res;
        $user = json_decode(wp_remote_retrieve_body($user_res), true);
        $email = isset($user['email']) ? $user['email'] : '';
        $name = isset($user['name']) ? $user['name'] : (isset($user['given_name']) ? $user['given_name'] : 'Google');
        $uid = isset($user['id']) ? $user['id'] : '';
        if (!$email) {
            $email = $uid ? $uid . '@google.com' : uniqid('gg_') . '@temp.local';
        }
        return $this->get_or_create_user($email, $name, 'google', $uid);
    }

    // ── Общий callback ────────────────────────────────

    public function callback() {
        $provider = isset($_GET['provider']) ? sanitize_text_field($_GET['provider']) : '';
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';

        $data = get_transient('nr_oauth_' . md5($state));
        if (!$data || $data['provider'] !== $provider) {
            wp_redirect(home_url());
            exit;
        }
        // Verify session binding to prevent forced-login attacks
        if (!empty($data['session_hash']) && $data['session_hash'] !== self::get_session_hash()) {
            delete_transient('nr_oauth_' . md5($state));
            wp_redirect(home_url());
            exit;
        }
        delete_transient('nr_oauth_' . md5($state));
        $post_id = (int) $data['post_id'];

        $method = $provider . '_get_user';
        if (!method_exists($this, $method)) {
            wp_redirect(get_permalink($post_id) ?: home_url());
            exit;
        }
        $user_id = $this->$method($code, $data);

        if (is_wp_error($user_id)) {
            wp_redirect(add_query_arg('nr_error', urlencode($user_id->get_error_message()), get_permalink($post_id) ?: home_url()));
            exit;
        }
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        wp_redirect(get_permalink($post_id) ?: home_url('/'));
        exit;
    }

    // ── Helpers ─────────────────────────────────────────

    /**
     * Session hash for OAuth state binding.
     * Uses browser cookies to bind OAuth flow to the initiating browser.
     */
    private static function get_session_hash() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return md5($ip . '|' . $ua . '|' . NONCE_SALT);
    }

    /**
     * Find existing user by provider UID or email, or create new one.
     */
    private function get_or_create_user($email, $name, $provider, $provider_uid) {
        // First try to find by provider UID (strong binding)
        if ($provider_uid) {
            $existing = get_users([
                'meta_key'   => 'nr_social_uid_' . $provider,
                'meta_value' => $provider_uid,
                'number'     => 1,
                'fields'     => 'ID',
            ]);
            if (!empty($existing)) {
                return (int) $existing[0];
            }
        }

        // Fallback to email
        $user_id = email_exists($email);
        if ($user_id) {
            // Store provider UID for future lookups
            if ($provider_uid) {
                update_user_meta($user_id, 'nr_social_uid_' . $provider, $provider_uid);
            }
            return $user_id;
        }

        // Create new user
        $login = sanitize_user(str_replace(' ', '_', $name), true);
        if (!$login) {
            $login = $provider . '_user';
        }
        if (username_exists($login)) {
            $login = $login . '_' . substr(md5($email), 0, 6);
        }
        $user_id = wp_create_user($login, wp_generate_password(24, true), $email);
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        wp_update_user(['ID' => $user_id, 'display_name' => $name]);
        update_user_meta($user_id, 'nr_social_provider', $provider);
        if ($provider_uid) {
            update_user_meta($user_id, 'nr_social_uid_' . $provider, $provider_uid);
        }
        return $user_id;
    }
}
