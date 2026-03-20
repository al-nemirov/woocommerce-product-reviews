<?php
/**
 * Helper: force-enable product reviews on WooCommerce product pages.
 *
 * Some themes or configurations disable comments on WooCommerce products.
 * Include this file in your theme's functions.php to ensure reviews work:
 *
 *   require_once get_stylesheet_directory() . '/path-to/smart-product-reviews/fix-comments-display.php';
 *
 * @package SmartProductReviews
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'nr_fix_product_comments_support' ) ) {
	add_action( 'init', 'nr_fix_product_comments_support', 20 );

	/**
	 * Add 'comments' support to the 'product' post type.
	 *
	 * Ensures the product post type declares comment support,
	 * which some themes may have removed.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	function nr_fix_product_comments_support() {
		add_post_type_support( 'product', 'comments' );
	}
}

if ( ! function_exists( 'nr_fix_product_comments_open' ) ) {
	add_filter( 'comments_open', 'nr_fix_product_comments_open', 10, 2 );

	/**
	 * Force comments open on product pages.
	 *
	 * Overrides any theme or plugin that closes comments on products.
	 *
	 * @since  1.0.0
	 * @param  bool $open    Whether comments are open.
	 * @param  int  $post_id The post ID.
	 * @return bool True for products, original value otherwise.
	 */
	function nr_fix_product_comments_open( $open, $post_id ) {
		if ( $post_id && get_post_type( $post_id ) === 'product' ) {
			return true;
		}
		return $open;
	}
}

if ( ! function_exists( 'nr_fix_woocommerce_reviews_enabled' ) ) {
	add_filter( 'pre_option_woocommerce_enable_reviews', 'nr_fix_woocommerce_reviews_enabled', 5, 2 );

	/**
	 * Force WooCommerce reviews to be enabled.
	 *
	 * Overrides the 'woocommerce_enable_reviews' option to always return 'yes'.
	 *
	 * @since  1.0.0
	 * @param  mixed  $pre    The pre-filtered option value.
	 * @param  string $option The option name.
	 * @return string Always returns 'yes'.
	 */
	function nr_fix_woocommerce_reviews_enabled( $pre, $option ) {
		return 'yes';
	}
}
