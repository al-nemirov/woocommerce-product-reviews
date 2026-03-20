<?php
/**
 * Plugin Name: Smart Product Reviews
 * Plugin URI:  https://github.com/al-nemirov/smart-product-reviews
 * Description: WooCommerce product reviews with star ratings, emoji editor, editor notes, and shortcodes.
 * Version: 1.0.1
 * Author: Alexander Nemirov
 * Author URI:  https://github.com/al-nemirov
 * Text Domain: smart-product-reviews
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 *
 * @package SmartProductReviews
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin version constant.
 *
 * @since 1.0.0
 * @var string
 */
define('NR_VERSION', '1.0.1');

/**
 * Plugin directory path (with trailing slash).
 *
 * @since 1.0.0
 * @var string
 */
define('NR_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL (with trailing slash).
 *
 * @since 1.0.0
 * @var string
 */
define('NR_URL', plugin_dir_url(__FILE__));

require_once NR_PATH . 'includes/class-nr-core.php';

/**
 * Return the singleton instance of the plugin core.
 *
 * @since  1.0.0
 * @return NR_Core Plugin core instance.
 */
function spr_instance() {
    return NR_Core::instance();
}

/**
 * Detect Elementor editor context -- skip rendering review form to avoid nested HTML errors.
 *
 * Checks multiple conditions: admin Elementor action, elementor-preview parameter,
 * REST API edit context, and Elementor plugin edit/preview mode.
 *
 * @since  1.0.0
 * @return bool True if currently in an Elementor editor or preview context.
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
 * Render product reviews block in a theme template.
 *
 * Can be called directly in template files. If no product ID is provided,
 * it attempts to use the current product page ID.
 *
 * Usage:
 *   <?php nr_product_reviews(); ?>
 *   <?php nr_product_reviews( 123 ); ?>
 *
 * @since 1.0.0
 * @param int $product_id Optional. WooCommerce product ID. Default 0 (auto-detect).
 * @return void
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

/**
 * Initialize the plugin after all plugins are loaded.
 *
 * Checks that WooCommerce is active before initializing.
 * Displays an admin notice if WooCommerce is not found.
 *
 * @since 1.0.0
 */
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning"><p>Smart Product Reviews requires WooCommerce.</p></div>';
        });
        return;
    }
    spr_instance()->init();
});

/**
 * Run activation tasks when the plugin is activated.
 *
 * Creates the secret editor login page and sets default options.
 *
 * @since 1.0.0
 */
register_activation_hook(__FILE__, function () {
    if (class_exists('WooCommerce')) {
        spr_instance()->activate();
    }
});
