<?php
/**
 * Star rating handler.
 *
 * Manages rating sync between comments and WooCommerce product meta,
 * ensures rating meta exists on all comments, and provides
 * a helper to render star rating HTML.
 *
 * @package SmartProductReviews
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NR_Rating
 *
 * Singleton class responsible for star rating storage,
 * WooCommerce sync, and HTML rendering.
 *
 * @since 1.0.0
 */
class NR_Rating {

    /**
     * Singleton instance.
     *
     * @since 1.0.0
     * @var NR_Rating|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @since  1.0.0
     * @return NR_Rating
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register WordPress hooks for rating sync.
     *
     * @since  1.0.0
     * @return void
     */
    public function init() {
        add_action('comment_post', [$this, 'sync_rating'], 10, 3);
        add_filter('comments_array', [$this, 'ensure_rating_meta'], 10, 2);
    }

    /**
     * Save the star rating when a comment is posted and sync to WooCommerce.
     *
     * Hooked to 'comment_post'. Only processes approved comments with
     * a valid rating (1-5). Updates both the comment meta and the
     * WooCommerce product rating aggregates.
     *
     * @since  1.0.0
     * @param  int        $comment_id  The comment ID.
     * @param  int|string $approved    Comment approval status (1 = approved).
     * @param  array      $commentdata Comment data array.
     * @return void
     */
    public function sync_rating($comment_id, $approved, $commentdata) {
        if ($approved !== 1) {
            return;
        }
        $rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
        if ($rating >= 1 && $rating <= 5) {
            add_comment_meta($comment_id, 'rating', $rating, true);
            $comment = get_comment($comment_id);
            if ($comment && get_post_type($comment->comment_post_ID) === 'product') {
                $this->update_product_rating($comment->comment_post_ID);
            }
        }
    }

    /**
     * Ensure every comment has a 'rating' meta key.
     *
     * Hooked to 'comments_array'. Adds a default rating of 0 for
     * comments that are missing the meta, preventing errors in templates.
     *
     * @since  1.0.0
     * @param  array $comments Array of WP_Comment objects.
     * @param  int   $post_id  The post ID.
     * @return array Unmodified comments array.
     */
    public function ensure_rating_meta($comments, $post_id) {
        foreach ($comments as $c) {
            if (!get_comment_meta($c->comment_ID, 'rating', true)) {
                add_comment_meta($c->comment_ID, 'rating', 0, true);
            }
        }
        return $comments;
    }

    /**
     * Recalculate and update the average rating for a WooCommerce product.
     *
     * Queries all approved comments with a rating > 0, computes the average,
     * and updates both custom plugin meta and WooCommerce native meta fields.
     * Also clears WooCommerce product transients.
     *
     * @since  1.0.0
     * @param  int $product_id WooCommerce product ID.
     * @return void
     */
    private function update_product_rating($product_id) {
        $comments = get_comments([
            'post_id' => $product_id,
            'status'  => 'approve',
            'meta_query' => [['key' => 'rating', 'value' => 0, 'compare' => '>']],
        ]);
        $total = 0;
        $count = 0;
        foreach ($comments as $c) {
            $r = (int) get_comment_meta($c->comment_ID, 'rating', true);
            if ($r > 0) {
                $total += $r;
                $count++;
            }
        }
        $average = $count > 0 ? $total / $count : 0;
        update_post_meta($product_id, '_nr_rating_avg', round($average, 2));
        update_post_meta($product_id, '_nr_rating_count', $count);
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
        update_post_meta($product_id, '_wc_average_rating', round($average, 2));
        update_post_meta($product_id, '_wc_review_count', $count);
    }

    /**
     * Generate HTML for a star rating display.
     *
     * Renders full, half, and empty stars as Unicode characters
     * wrapped in span elements with appropriate CSS classes.
     *
     * @since  1.0.0
     * @param  float $rating Star rating value (0-5).
     * @param  int   $size   Optional. Font size in pixels (reserved for future use). Default 20.
     * @return string HTML string with star rating markup.
     */
    public static function get_rating_html($rating, $size = 20) {
        $rating = max(0, min(5, (float) $rating));
        $full = floor($rating);
        $half = ($rating - $full) >= 0.5;
        $empty = 5 - $full - ($half ? 1 : 0);
        $html = '<span class="nr-stars" title="' . esc_attr($rating) . '">';
        for ($i = 0; $i < $full; $i++) {
            $html .= '<span class="nr-star nr-full">★</span>';
        }
        if ($half) {
            $html .= '<span class="nr-star nr-half">★</span>';
        }
        for ($i = 0; $i < $empty; $i++) {
            $html .= '<span class="nr-star nr-empty">★</span>';
        }
        $html .= '</span>';
        return $html;
    }
}
