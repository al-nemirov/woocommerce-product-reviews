<?php
if (!defined('ABSPATH')) {
    exit;
}

class NR_Rating {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('comment_post', [$this, 'sync_rating'], 10, 3);
        add_filter('comments_array', [$this, 'ensure_rating_meta'], 10, 2);
    }

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

    public function ensure_rating_meta($comments, $post_id) {
        foreach ($comments as $c) {
            if (!get_comment_meta($c->comment_ID, 'rating', true)) {
                add_comment_meta($c->comment_ID, 'rating', 0, true);
            }
        }
        return $comments;
    }

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
