<?php
if (!defined('ABSPATH')) {
    exit;
}
$post_id = get_the_ID();
if (!$post_id || get_post_type($post_id) !== 'product') {
    return;
}

$user = wp_get_current_user();
$can_edit = NR_Core::can_manage_editor_notes($user);
$editor_note = get_post_meta($post_id, '_nr_editor_note', true);
$editor_note_author = get_post_meta($post_id, '_nr_editor_note_author', true);
if (!is_string($editor_note)) {
    $editor_note = '';
}
if (!is_string($editor_note_author)) {
    $editor_note_author = '';
}

if (nr_is_editor_context()) {
    echo '<div id="nr-editor-note" class="nr-editor-note"><p class="nr-editor-placeholder">' . esc_html__('Editor note placeholder (visible on frontend only).', 'woocommerce-product-reviews') . '</p></div>';
    return;
}

if ($can_edit) {
    wp_enqueue_editor();
    wp_enqueue_media();
    wp_enqueue_style('nr-comments', NR_URL . 'assets/css/comments.css', [], NR_VERSION);
}

// Rating data
$avg_rating   = (float) get_post_meta($post_id, '_nr_rating_avg', true);
$rating_count = (int) get_post_meta($post_id, '_nr_rating_count', true);
$is_logged_in = is_user_logged_in();
$thread_depth = (int) NR_Core::instance()->get_option('thread_depth', 1);
$per_page     = (int) NR_Core::instance()->get_option('comments_per_page', 10);

// Comments tree (page 1)
$comments_tree = NR_Comments::get_comments_tree($post_id, 1);
$total_parents = (int) get_comments([
    'post_id' => $post_id,
    'type'    => 'review',
    'status'  => 'approve',
    'parent'  => 0,
    'count'   => true,
]);
$total_pages = max(1, (int) ceil($total_parents / $per_page));
?>

<!-- ═══ Editor note ═══ -->
<div id="nr-editor-note" class="nr-editor-note">
    <h3 class="nr-title"><?php echo esc_html__('Editor note', 'woocommerce-product-reviews'); ?></h3>

    <div class="nr-editor-note-content">
        <?php if ($editor_note_author) : ?><p class="nr-editor-note-by"><?php echo esc_html__('Note:', 'woocommerce-product-reviews'); ?> <strong><?php echo esc_html($editor_note_author); ?></strong></p><?php endif; ?>
        <?php echo $editor_note ? wp_kses_post($editor_note) : '<p class="nr-no-note">' . esc_html__('No editor note yet.', 'woocommerce-product-reviews') . '</p>'; ?>
    </div>
    <?php if ($can_edit) : ?>
        <p class="nr-editor-note-actions">
            <button type="button" class="nr-edit-note nr-submit"><?php echo esc_html__('Edit note', 'woocommerce-product-reviews'); ?></button>
        </p>
        <form id="nr-editor-note-form" class="nr-editor-note-form" method="post" action="" data-post-id="<?php echo (int) $post_id; ?>" style="display:none;">
            <?php wp_nonce_field('nr_save_editor_note', 'nr_editor_nonce'); ?>
            <input type="hidden" name="nr_editor_note_form" value="1" />
            <input type="hidden" name="nr_editor_note_post" value="<?php echo (int) $post_id; ?>" />
            <?php
            wp_editor($editor_note, 'nr_editor_note_' . $post_id, [
                'textarea_name' => 'nr_editor_note_content',
                'textarea_rows' => 14,
                'teeny'        => false,
                'media_buttons'=> true,
                'quicktags'    => true,
                'tinymce'      => [
                    'paste_as_text'          => false,
                    'paste_data_images'      => true,
                    'paste_remove_styles'    => false,
                    'paste_remove_styles_if_webkit' => false,
                ],
                'wpautop'      => true,
            ]);
            ?>
            <p><button type="submit" class="nr-submit nr-save-note"><?php echo esc_html__('Save note', 'woocommerce-product-reviews'); ?></button></p>
            <p class="nr-form-message" style="display:none;"></p>
        </form>
    <?php endif; ?>
</div>

<!-- ═══ Note questions (chat) ═══ -->
<?php $note_questions = NR_Comments::get_note_questions($post_id); ?>
<div id="nr-note-questions" class="nr-note-questions" data-post-id="<?php echo (int) $post_id; ?>">
    <h3 class="nr-title"><?php echo esc_html__('Questions about this note', 'woocommerce-product-reviews'); ?></h3>

    <div class="nr-note-chat" id="nr-note-chat">
        <?php foreach ($note_questions as $q) : ?>
            <?php echo NR_Comments::render_note_question_html($q); ?>
        <?php endforeach; ?>
    </div>

    <form id="nr-note-question-form" class="nr-form" data-post-id="<?php echo (int) $post_id; ?>">
        <p>
            <textarea name="note_question" rows="3" placeholder="<?php echo esc_attr__('Your question...', 'woocommerce-product-reviews'); ?>" required></textarea>
        </p>

        <?php if (!$is_logged_in) : ?>
            <?php echo NR_Social::render_buttons($post_id); ?>
            <p class="nr-or"><?php echo esc_html__('or leave a review as a guest:', 'woocommerce-product-reviews'); ?></p>
            <p>
                <input type="text" name="author" placeholder="<?php echo esc_attr__('Name', 'woocommerce-product-reviews'); ?>" required />
                <input type="email" name="email" placeholder="<?php echo esc_attr__('Email', 'woocommerce-product-reviews'); ?>" required />
            </p>
        <?php endif; ?>

        <p>
            <button type="submit" class="nr-submit"><?php echo esc_html__('Ask a question', 'woocommerce-product-reviews'); ?></button>
        </p>
        <p class="nr-form-message" style="display:none;"></p>
    </form>
</div>

<!-- ═══ Reviews section ═══ -->
<div id="nr-reviews" class="nr-reviews" data-post-id="<?php echo (int) $post_id; ?>" data-page="1" data-total-pages="<?php echo (int) $total_pages; ?>">

    <!-- Review form -->
    <h3 class="nr-title"><?php echo esc_html__('Leave a review', 'woocommerce-product-reviews'); ?></h3>

    <div class="nr-form-wrap">
        <form id="nr-comment-form" class="nr-form" data-post-id="<?php echo (int) $post_id; ?>">
            <input type="hidden" name="comment_parent" value="0" />

            <div class="nr-rating-input">
                <label><?php echo esc_html__('Rating:', 'woocommerce-product-reviews'); ?></label>
                <span class="nr-stars-edit" data-rating="0">
                    <span class="nr-star" data-v="1">&#9733;</span>
                    <span class="nr-star" data-v="2">&#9733;</span>
                    <span class="nr-star" data-v="3">&#9733;</span>
                    <span class="nr-star" data-v="4">&#9733;</span>
                    <span class="nr-star" data-v="5">&#9733;</span>
                </span>
                <input type="hidden" name="rating" value="0" />
            </div>

            <p>
                <textarea name="content" rows="4" placeholder="<?php echo esc_attr__('Your review...', 'woocommerce-product-reviews'); ?>" required></textarea>
            </p>

            <?php if (!$is_logged_in) : ?>
                <!-- Social login buttons -->
                <?php echo NR_Social::render_buttons($post_id); ?>

                <p class="nr-or"><?php echo esc_html__('or leave a review as a guest:', 'woocommerce-product-reviews'); ?></p>
                <p>
                    <input type="text" name="author" placeholder="<?php echo esc_attr__('Name', 'woocommerce-product-reviews'); ?>" required />
                    <input type="email" name="email" placeholder="<?php echo esc_attr__('Email', 'woocommerce-product-reviews'); ?>" required />
                </p>
            <?php endif; ?>

            <p>
                <button type="submit" class="nr-submit"><?php echo esc_html__('Submit review', 'woocommerce-product-reviews'); ?></button>
            </p>
            <p class="nr-form-message" style="display:none;"></p>
        </form>
    </div>

    <!-- Comments list -->
    <div id="nr-comments-list" class="nr-comments-list">
        <?php foreach ($comments_tree as $comment) : ?>
            <?php echo NR_Comments::render_comment_html($comment); ?>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1) : ?>
    <div class="nr-pagination" data-post-id="<?php echo (int) $post_id; ?>">
        <button type="button" class="nr-load-more nr-submit"><?php echo esc_html__('Load more reviews', 'woocommerce-product-reviews'); ?></button>
    </div>
    <?php endif; ?>

</div>
