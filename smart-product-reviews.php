<?php
/**
 * Plugin Name: Smart Product Reviews
 * Description: WooCommerce product reviews with star ratings, emoji editor, editor notes, and shortcodes.
 * Version: 1.0.0
 * Author: Alexander Nemirov
 * Text Domain: smart-product-reviews
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NR_VERSION', '1.0.0');
define('NR_PATH', plugin_dir_path(__FILE__));
define('NR_URL', plugin_dir_url(__FILE__));

require_once NR_PATH . 'includes/class-nr-core.php';

function spr_instance() {
    return NR_Core::instance();
}

/**
 * Detect Elementor editor context — skip rendering review form to avoid nested HTML errors.
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
 * Render product reviews block in theme template.
 * Usage: <?php nr_product_reviews(); ?> or <?php nr_product_reviews( 123 ); ?>
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
            echo '<div class="notice notice-warning"><p>Smart Product Reviews requires WooCommerce.</p></div>';
        });
        return;
    }
    spr_instance()->init();
});

register_activation_hook(__FILE__, function () {
    if (class_exists('WooCommerce')) {
        spr_instance()->activate();
    }
});
