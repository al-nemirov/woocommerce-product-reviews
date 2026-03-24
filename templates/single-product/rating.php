<?php
/**
 * Single Product Rating (overrides WooCommerce template)
 *
 * Убирает текст "на основе опроса X пользователей / X отзывов клиентов"
 * и заменяет на простой "X отзывов".
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! wc_review_ratings_enabled() ) {
	return;
}

$rating_count = $product->get_rating_count();
$review_count = $product->get_review_count();
$average      = $product->get_average_rating();

if ( $rating_count <= 0 ) {
	return;
}

$review_text = NR_Core::plural_reviews( $review_count );
?>
<div class="woocommerce-product-rating">
	<?php echo wc_get_rating_html( $average, $rating_count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php if ( comments_open() ) : ?>
		<a href="#nr-reviews" class="woocommerce-review-link" rel="nofollow">(<?php echo esc_html( $review_text ); ?>)</a>
	<?php endif; ?>
</div>
