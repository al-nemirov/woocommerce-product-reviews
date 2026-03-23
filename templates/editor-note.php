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
$has_note = !empty(trim($editor_note));
$note_title = NR_Core::get_editor_note_title();

// Nothing to show for regular users if no note exists
if (!$has_note && !$can_edit) {
    return;
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
?>
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
