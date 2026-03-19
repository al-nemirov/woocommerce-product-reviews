(function($) {
    $(function() {
        if (typeof nrData === 'undefined' || !nrData.ajax_url) return;

        // Примечание редактора — сохранение через TinyMCE
        var $noteForm = $('#nr-editor-note-form');
        if ($noteForm.length) {
            var notePostId = $noteForm.data('post-id');
            var editorId = 'nr_editor_note_' + notePostId;
            var $noteMsg = $noteForm.find('.nr-form-message');
            var $box = $('#nr-editor-note');
            var $content = $box.find('.nr-editor-note-content');
            var $editBtn = $box.find('.nr-edit-note');

            // Открыть/закрыть форму по кнопке
            $editBtn.off('click.nr').on('click.nr', function() {
                $noteForm.toggle();
                $noteMsg.hide();
                if ($noteForm.is(':visible') && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                    tinymce.get(editorId).focus();
                }
            });

            $noteForm.off('submit.note').on('submit.note', function(e) {
                e.preventDefault();
                var content = '';
                if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                    content = tinymce.get(editorId).getContent();
                } else {
                    content = $('#' + editorId).val() || '';
                }
                $noteMsg.hide();
                $noteForm.find('.nr-save-note').prop('disabled', true).text('Сохранение…');
                $.post(nrData.ajax_url, {
                    action: 'nr_save_editor_note',
                    nonce: nrData.editor_note_nonce || '',
                    post_id: notePostId,
                    content: content
                }, null, 'json').done(function(r) {
                    if (r.success) {
                        // Обновляем текст примечания на странице
                        if ($content.length) {
                            var $author = $content.find('.nr-editor-note-by').first();
                            var authorHtml = $author.length ? $author[0].outerHTML : '';
                            $content.html(authorHtml + content);
                        }
                        $noteMsg.removeClass('error').addClass('success').text(r.data && r.data.message ? r.data.message : 'Сохранено.').show();
                        $noteForm.hide();
                    } else {
                        $noteMsg.removeClass('success').addClass('error').text(r.data && r.data.message ? r.data.message : 'Ошибка').show();
                    }
                }).fail(function() {
                    $noteMsg.removeClass('success').addClass('error').text('Ошибка сети').show();
                }).always(function() {
                    $noteForm.find('.nr-save-note').prop('disabled', false).text('Сохранить примечание');
                });
            });
        }

        var $form = $('#nr-comment-form');
        if (!$form.length) return;

        var postId = $form.data('post-id');
        if (!postId) return;
        var $msg = $form.find('.nr-form-message');
        var $rating = $form.find('.nr-stars-edit');
        var $ratingInput = $form.find('input[name="rating"]');

        $rating.off('click.nr').on('click.nr', '.nr-star', function() {
            var v = $(this).data('v');
            $rating.data('rating', v);
            $ratingInput.val(v);
            $rating.find('.nr-star').removeClass('active').each(function(i) {
                if (i < v) $(this).addClass('active');
            });
        });

        $form.off('submit.nr').on('submit.nr', function(e) {
            e.preventDefault();
            $msg.hide();
            var data = {
                action: 'nr_submit_comment',
                nonce: typeof nrData !== 'undefined' ? nrData.nonce : '',
                post_id: postId,
                content: $form.find('[name="content"]').val(),
                rating: $ratingInput.val() || 0
            };
            if ($form.find('[name="author"]').length) {
                data.author = $form.find('[name="author"]').val();
                data.email = $form.find('[name="email"]').val();
            }
            $.post(nrData.ajax_url, data, null, 'json')
                .done(function(r) {
                    if (r.success) {
                        $msg.removeClass('error').addClass('success').text(r.data.message).show();
                        $form.find('[name="content"]').val('');
                        $ratingInput.val(0);
                        $rating.data('rating', 0);
                        if (r.data.comment_id) {
                            $msg.text('Спасибо! Отзыв отправлен. Обновите страницу, чтобы увидеть его.');
                        }
                    } else {
                        $msg.removeClass('success').addClass('error').text(r.data && r.data.message ? r.data.message : 'Ошибка').show();
                    }
                })
                .fail(function() {
                    $msg.removeClass('success').addClass('error').text('Ошибка сети').show();
                });
        });

        $(document).off('click.nr', '.nr-btn[data-provider]').on('click.nr', '.nr-btn[data-provider]', function() {
            var provider = $(this).data('provider');
            var pid = $(this).data('post-id');
            $.post(nrData.ajax_url, {
                action: 'nr_social_login',
                provider: provider,
                post_id: pid
            }).done(function(r) {
                if (r.success && r.data && r.data.url) {
                    window.location.href = r.data.url;
                } else {
                    alert(r.data && r.data.message ? r.data.message : 'Ошибка входа');
                }
            });
        });
    });
})(jQuery);
