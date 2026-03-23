<?php
/**
 * Smart Product Reviews: принудительное включение отзывов на странице товара
 * Подключите в functions.php темы одной строкой:
 * require_once get_stylesheet_directory() . '/path-to/smart-product-reviews/fix-comments-display.php';
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'nr_fix_product_comments_support' ) ) {
	add_action( 'init', 'nr_fix_product_comments_support', 20 );
	function nr_fix_product_comments_support() {
		add_post_type_support( 'product', 'comments' );
	}
}

if ( ! function_exists( 'nr_fix_product_comments_open' ) ) {
	add_filter( 'comments_open', 'nr_fix_product_comments_open', 10, 2 );
	function nr_fix_product_comments_open( $open, $post_id ) {
		if ( $post_id && get_post_type( $post_id ) === 'product' ) {
			return true;
		}
		return $open;
	}
}

if ( ! function_exists( 'nr_fix_woocommerce_reviews_enabled' ) ) {
	add_filter( 'pre_option_woocommerce_enable_reviews', 'nr_fix_woocommerce_reviews_enabled', 5, 2 );
	function nr_fix_woocommerce_reviews_enabled( $pre, $option ) {
		return 'yes';
	}
}
