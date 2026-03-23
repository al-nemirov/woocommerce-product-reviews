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
    echo '<div id="nr-editor-note" class="nr-editor-note"><p class="nr-editor-placeholder">' . esc_html__('Editor note placeholder (visible on frontend only).', 'smart-product-reviews') . '</p></div>';
    return;
}

if ($can_edit) {
    wp_enqueue_editor();
    wp_enqueue_media();
    wp_enqueue_style('nr-comments', NR_URL . 'assets/css/comments.css', [], NR_VERSION);
}
?>
<div id="nr-editor-note" class="nr-editor-note">
    <h3 class="nr-title"><?php echo esc_html__('Editor note', 'smart-product-reviews'); ?></h3>

    <div class="nr-editor-note-content">
        <?php if ($editor_note_author) : ?><p class="nr-editor-note-by"><?php echo esc_html__('Note:', 'smart-product-reviews'); ?> <strong><?php echo esc_html($editor_note_author); ?></strong></p><?php endif; ?>
        <?php echo $editor_note ? wp_kses_post($editor_note) : '<p class="nr-no-note">' . esc_html__('No editor note yet.', 'smart-product-reviews') . '</p>'; ?>
    </div>
    <?php if ($can_edit) : ?>
        <p class="nr-editor-note-actions">
            <button type="button" class="nr-edit-note nr-submit"><?php echo esc_html__('Edit note', 'smart-product-reviews'); ?></button>
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
            <p><button type="submit" class="nr-submit nr-save-note"><?php echo esc_html__('Save note', 'smart-product-reviews'); ?></button></p>
            <p class="nr-form-message" style="display:none;"></p>
        </form>
    <?php endif; ?>
</div>
