<?php
/**
 * Comments template for WooCommerce product pages.
 *
 * Displays the editor note block with view/edit functionality.
 * Shows a TinyMCE editor form for users with edit capabilities.
 * In Elementor editor context, shows a placeholder instead.
 *
 * @package SmartProductReviews
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
$nr = spr_instance();
$post_id = get_the_ID();
if (!$post_id || get_post_type($post_id) !== 'product') {
    return;
}

$user = wp_get_current_user();
$can_edit = current_user_can( 'manage_review_notes' );
$editor_note = get_post_meta($post_id, '_nr_editor_note', true);
$editor_note_author = get_post_meta($post_id, '_nr_editor_note_author', true);
if (!is_string($editor_note)) {
    $editor_note = '';
}
if (!is_string($editor_note_author)) {
    $editor_note_author = '';
}

if (nr_is_editor_context()) {
    echo '<div id="nr-editor-note" class="nr-editor-note"><p class="nr-editor-placeholder">Editor note — displayed on the site.</p></div>';
    return;
}

if ($can_edit) {
    wp_enqueue_editor();
    wp_enqueue_media();
    wp_enqueue_style('nr-comments', NR_URL . 'assets/css/comments.css', [], NR_VERSION);
}
?>
<div id="nr-editor-note" class="nr-editor-note">
    <h3 class="nr-title">Editor Note</h3>

    <div class="nr-editor-note-content">
        <?php if ($editor_note_author) : ?><p class="nr-editor-note-by">Note by: <strong><?php echo esc_html($editor_note_author); ?></strong></p><?php endif; ?>
        <?php echo $editor_note ? wp_kses_post($editor_note) : '<p class="nr-no-note">No note added yet.</p>'; ?>
    </div>
    <?php if ($can_edit) : ?>
        <p class="nr-editor-note-actions">
            <button type="button" class="nr-edit-note nr-submit">Edit Note</button>
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
            <p><button type="submit" class="nr-submit nr-save-note">Save Note</button></p>
            <p class="nr-form-message" style="display:none;"></p>
        </form>
    <?php endif; ?>
</div>
