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

$is_logged_in = is_user_logged_in();
$thread_depth = (int) NR_Core::instance()->get_option('thread_depth', 1);
$per_page     = (int) NR_Core::instance()->get_option('comments_per_page', 10);
$note_title   = NR_Core::get_editor_note_title();

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
$has_note = !empty(trim($editor_note));
?>

<!-- ═══ Editor note (optional) ═══ -->
<?php if ($has_note || $can_edit) : ?>
<div id="nr-editor-note" class="nr-editor-note">
    <?php if ($has_note) : ?>
        <h3 class="nr-title"><?php echo esc_html($note_title); ?></h3>
        <div class="nr-editor-note-content">
            <?php if ($editor_note_author) : ?><p class="nr-editor-note-by"><strong><?php echo esc_html($editor_note_author); ?></strong></p><?php endif; ?>
            <?php echo wp_kses_post($editor_note); ?>
        </div>
    <?php endif; ?>

    <?php if ($can_edit) : ?>
        <?php if (!$has_note) : ?>
            <label class="nr-editor-note-toggle">
                <input type="checkbox" id="nr-toggle-note-form" />
                <?php echo esc_html__('Оставить примечание к книге', 'woocommerce-product-reviews'); ?>
            </label>
        <?php else : ?>
            <p class="nr-editor-note-actions">
                <button type="button" class="nr-edit-note nr-submit"><?php echo esc_html__('Edit note', 'woocommerce-product-reviews'); ?></button>
            </p>
        <?php endif; ?>
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
<?php endif; ?>

<!-- ═══ Reviews section (single stream) ═══ -->
<div id="nr-reviews" class="nr-reviews" data-post-id="<?php echo (int) $post_id; ?>" data-page="1" data-total-pages="<?php echo (int) $total_pages; ?>">

    <h3 class="nr-title"><?php echo esc_html__('Отзывы', 'woocommerce-product-reviews'); ?></h3>

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

    <!-- Review form -->
    <div class="nr-form-wrap">
        <?php if ($is_logged_in) : ?>
            <form id="nr-comment-form" class="nr-form" data-post-id="<?php echo (int) $post_id; ?>">
                <input type="hidden" name="comment_parent" value="0" />

                <div class="nr-rating-input">
                    <label><?php echo esc_html__('Оценка (необязательно):', 'woocommerce-product-reviews'); ?></label>
                    <span class="nr-stars-edit" data-rating="0">
                        <span class="nr-star" data-v="1">&#9733;</span>
                        <span class="nr-star" data-v="2">&#9733;</span>
                        <span class="nr-star" data-v="3">&#9733;</span>
                        <span class="nr-star" data-v="4">&#9733;</span>
                        <span class="nr-star" data-v="5">&#9733;</span>
                    </span>
                    <input type="hidden" name="rating" value="0" />
                </div>

                <div class="nr-textarea-wrap">
                    <textarea name="content" rows="4" placeholder="<?php echo esc_attr__('Ваш отзыв...', 'woocommerce-product-reviews'); ?>" required></textarea>
                    <button type="button" class="nr-emoji-toggle" title="Emoji">😊</button>
                    <div class="nr-emoji-picker" style="display:none;">
                        <?php
                        $emojis = ['😊','😍','👍','❤️','🔥','😂','🤔','👏','💯','⭐','📚','📖','✨','🎉','😎','🥰','😢','😠','👎','💔'];
                        foreach ($emojis as $e) {
                            echo '<span class="nr-emoji" data-emoji="' . $e . '">' . $e . '</span>';
                        }
                        ?>
                    </div>
                </div>

                <p>
                    <button type="submit" class="nr-submit"><?php echo esc_html__('Отправить', 'woocommerce-product-reviews'); ?></button>
                </p>
                <p class="nr-form-message" style="display:none;"></p>
            </form>
        <?php else : ?>
            <div class="nr-login-prompt">
                <p class="nr-login-text"><?php echo esc_html__('Войдите, чтобы оставить отзыв:', 'woocommerce-product-reviews'); ?></p>
                <?php echo NR_Social::render_buttons($post_id); ?>
            </div>
        <?php endif; ?>
    </div>

</div>
