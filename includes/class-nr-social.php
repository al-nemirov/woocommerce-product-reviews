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

    public function ajax_login() {
        if (!get_option('users_can_register')) {
            wp_send_json_error(['message' => 'Регистрация отключена.']);
        }
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $post_id  = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($provider === 'vk') {
            $this->vk_redirect($post_id);
        } elseif ($provider === 'yandex') {
            $this->yandex_redirect($post_id);
        }
        wp_send_json_error(['message' => 'Неизвестный провайдер.']);
    }

    private function vk_redirect($post_id) {
        $app_id = NR_Core::instance()->get_option('vk_app_id');
        if (!$app_id) {
            wp_send_json_error(['message' => 'VK не настроен.']);
        }
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=vk');
        $state = wp_create_nonce('nr_vk_' . $post_id);
        $verifier = bin2hex(random_bytes(32));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        set_transient('nr_oauth_' . md5($state), [
            'provider' => 'vk', 'post_id' => $post_id, 'code_verifier' => $verifier
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

    private function yandex_redirect($post_id) {
        $id = NR_Core::instance()->get_option('yandex_id');
        $secret = NR_Core::instance()->get_option('yandex_secret');
        if (!$id || !$secret) {
            wp_send_json_error(['message' => 'Yandex не настроен.']);
        }
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=yandex');
        $state = wp_create_nonce('nr_ya_' . $post_id);
        set_transient('nr_oauth_' . md5($state), ['provider' => 'yandex', 'post_id' => $post_id], 600);
        $url = 'https://oauth.yandex.ru/authorize?' . http_build_query([
            'client_id'     => $id,
            'redirect_uri'  => $callback,
            'response_type' => 'code',
            'state'         => $state,
        ]);
        wp_send_json_success(['url' => $url]);
    }

    public function callback() {
        $provider = isset($_GET['provider']) ? sanitize_text_field($_GET['provider']) : '';
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';

        $data = get_transient('nr_oauth_' . md5($state));
        if (!$data || $data['provider'] !== $provider) {
            wp_redirect(wp_get_referer() ?: home_url());
            exit;
        }
        delete_transient('nr_oauth_' . md5($state));
        $post_id = (int) $data['post_id'];
        $code_verifier = isset($data['code_verifier']) ? $data['code_verifier'] : '';

        if ($provider === 'vk') {
            $user_id = $this->vk_get_user($code, $code_verifier);
        } elseif ($provider === 'yandex') {
            $user_id = $this->yandex_get_user($code);
        } else {
            wp_redirect(get_permalink($post_id) ?: home_url());
            exit;
        }

        if (is_wp_error($user_id)) {
            wp_redirect(add_query_arg('nr_error', urlencode($user_id->get_error_message()), get_permalink($post_id) ?: home_url()));
            exit;
        }
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        wp_redirect(get_permalink($post_id) ?: home_url('/'));
        exit;
    }

    private function vk_get_user($code, $code_verifier = '') {
        $app_id = NR_Core::instance()->get_option('vk_app_id');
        $callback = admin_url('admin-ajax.php?action=nr_social_callback&provider=vk');
        $body = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $app_id,
            'code'          => $code,
            'redirect_uri'  => $callback,
        ];
        if ($code_verifier) {
            $body['code_verifier'] = $code_verifier;
        }
        $res = wp_remote_post('https://id.vk.ru/oauth2/auth', [
            'body' => $body,
        ]);
        if (is_wp_error($res)) {
            return $res;
        }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($body['access_token'])) {
            return new WP_Error('nr_vk', 'VK: нет доступа');
        }
        $token = $body['access_token'];
        $uid = isset($body['user_id']) ? $body['user_id'] : '';
        $user_res = wp_remote_get('https://id.vk.ru/userinfo?access_token=' . urlencode($token));
        if (is_wp_error($user_res)) {
            return $user_res;
        }
        $user = json_decode(wp_remote_retrieve_body($user_res), true);
        $email = isset($user['email']) ? $user['email'] : ($uid ? $uid . '@vk.id' : '');
        $name = trim((isset($user['given_name']) ? $user['given_name'] : '') . ' ' . (isset($user['family_name']) ? $user['family_name'] : ''));
        if (!$name && isset($user['name'])) {
            $name = $user['name'];
        }
        if (!$name) {
            $name = $uid ? 'VK_' . $uid : 'VK';
        }
        if (!$email) {
            $email = $uid ? $uid . '@vk.id' : uniqid('vk_') . '@temp.local';
        }
        return $this->get_or_create_user($email, $name, 'vk', $uid);
    }

    private function yandex_get_user($code) {
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
        if (is_wp_error($res)) {
            return $res;
        }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($body['access_token'])) {
            return new WP_Error('nr_ya', 'Yandex: нет токена');
        }
        $user_res = wp_remote_get('https://login.yandex.ru/info?format=json', [
            'headers' => ['Authorization' => 'OAuth ' . $body['access_token']],
        ]);
        if (is_wp_error($user_res)) {
            return $user_res;
        }
        $user = json_decode(wp_remote_retrieve_body($user_res), true);
        $email = isset($user['default_email']) ? $user['default_email'] : (isset($user['id']) ? $user['id'] . '@yandex.ru' : '');
        $name = isset($user['real_name']) ? $user['real_name'] : (isset($user['login']) ? $user['login'] : 'Yandex');
        $uid = isset($user['id']) ? $user['id'] : '';
        return $this->get_or_create_user($email, $name, 'yandex', $uid);
    }

    private function get_or_create_user($email, $name, $provider, $provider_uid) {
        $user_id = email_exists($email);
        if ($user_id) {
            return $user_id;
        }
        $login = sanitize_user(str_replace(' ', '_', $name), true);
        if (username_exists($login)) {
            $login = $login . '_' . substr(md5($email), 0, 6);
        }
        $user_id = wp_create_user($login, wp_generate_password(24, true), $email);
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        wp_update_user(['ID' => $user_id, 'display_name' => $name]);
        update_user_meta($user_id, 'nr_social_provider', $provider);
        return $user_id;
    }
}
