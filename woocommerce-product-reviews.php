<?php
/**
 * Plugin Name: WooCommerce Product Reviews
 * Plugin URI: https://github.com/al-nemirov/woocommerce-product-reviews
 * Description: Reviews and rating for WooCommerce. Social login: VK, OK, Yandex, Google. Threaded replies, pagination, editor notes and shortcodes.
 * Version: 2.8.1
 * Author: Alexander Nemirov
 * Author URI: https://github.com/al-nemirov
 * Text Domain: woocommerce-product-reviews
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NR_VERSION', '2.8.1');
define('NR_PATH', plugin_dir_path(__FILE__));
define('NR_URL', plugin_dir_url(__FILE__));

require_once NR_PATH . 'includes/class-nr-core.php';

// GitHub updater — checks releases for new versions in WP admin
if (is_admin()) {
    require_once NR_PATH . 'includes/class-nr-github-updater.php';
    new NR_GitHub_Updater(__FILE__, 'al-nemirov/woocommerce-product-reviews');
}

add_action('init', function () {
    load_plugin_textdomain('woocommerce-product-reviews', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/**
 * Сейчас запрос из редактора Elementor или админки — не рендерить форму отзывов (избегаем ошибок и вложенного HTML).
 */
function nr_is_editor_context() {
    if (is_admin() && isset($_GET['action']) && $_GET['action'] === 'elementor') {
        return true;
    }
    if (!empty($_GET['elementor-preview'])) {
        return true;
    }
    if (defined('REST_REQUEST') && REST_REQUEST && !empty($_GET['context']) && $_GET['context'] === 'edit') {
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
 * Вывод блока отзывов на странице товара (для вызова из шаблона темы).
 * Использование: <?php nr_product_reviews(); ?> или <?php nr_product_reviews( 123 ); ?>
 */
function nr_product_reviews( $product_id = 0 ) {
    if ( ! class_exists( 'NR_Comments' ) ) {
        return;
    }
    if ( ! $product_id && is_singular( 'product' ) ) {
        $product_id = get_the_ID();
    }
    if ( $product_id ) {
        echo NR_Comments::instance()->render_product_reviews_html( $product_id );
    }
}

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning"><p>' . esc_html__('WooCommerce Product Reviews requires WooCommerce to be installed.', 'woocommerce-product-reviews') . '</p></div>';
        });
        return;
    }
    NR_Core::instance()->init();
});

register_activation_hook(__FILE__, function () {
    if (class_exists('WooCommerce')) {
        NR_Core::instance()->activate();
    }
});
