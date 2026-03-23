(function($) {
    $(function() {
        if (typeof nrData === 'undefined' || !nrData.ajax_url) return;

        // ═══ Editor note — TinyMCE save ═══
        var $noteForm = $('#nr-editor-note-form');
        if ($noteForm.length) {
            var notePostId = $noteForm.data('post-id');
            var editorId = 'nr_editor_note_' + notePostId;
            var $noteMsg = $noteForm.find('.nr-form-message');
            var $box = $('#nr-editor-note');
            var $content = $box.find('.nr-editor-note-content');
            var $editBtn = $box.find('.nr-edit-note');

            // Toggle via "Edit note" button (when note already exists)
            $editBtn.off('click.nr').on('click.nr', function() {
                $noteForm.toggle();
                $noteMsg.hide();
                if ($noteForm.is(':visible') && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                    tinymce.get(editorId).focus();
                }
            });

            // Toggle via checkbox (when no note exists yet)
            var $toggleCheckbox = $('#nr-toggle-note-form');
            if ($toggleCheckbox.length) {
                $toggleCheckbox.off('change.nr').on('change.nr', function() {
                    if ($(this).is(':checked')) {
                        $noteForm.show();
                        $noteMsg.hide();
                        if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                            tinymce.get(editorId).focus();
                        }
                    } else {
                        $noteForm.hide();
                    }
                });
            }

            $noteForm.off('submit.note').on('submit.note', function(e) {
                e.preventDefault();
                var content = '';
                if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                    content = tinymce.get(editorId).getContent();
                } else {
                    content = $('#' + editorId).val() || '';
                }
                $noteMsg.hide();
                $noteForm.find('.nr-save-note').prop('disabled', true).text(nrData.i18n ? nrData.i18n.saving : 'Saving...');
                $.post(nrData.ajax_url, {
                    action: 'nr_save_editor_note',
                    nonce: nrData.editor_note_nonce || '',
                    post_id: notePostId,
                    content: content
                }, null, 'json').done(function(r) {
                    if (r.success) {
                        if ($content.length) {
                            var $author = $content.find('.nr-editor-note-by').first();
                            var authorHtml = $author.length ? $author[0].outerHTML : '';
                            $content.html(authorHtml + content);
                        }
                        $noteMsg.removeClass('error').addClass('success').text(r.data && r.data.message ? r.data.message : (nrData.i18n ? nrData.i18n.saved : 'Saved.')).show();
                        $noteForm.hide();
                    } else {
                        $noteMsg.removeClass('success').addClass('error').text(r.data && r.data.message ? r.data.message : (nrData.i18n ? nrData.i18n.error : 'Error')).show();
                    }
                }).fail(function() {
                    $noteMsg.removeClass('success').addClass('error').text(nrData.i18n ? nrData.i18n.network_error : 'Network error').show();
                }).always(function() {
                    $noteForm.find('.nr-save-note').prop('disabled', false).text(nrData.i18n ? nrData.i18n.save_note : 'Save note');
                });
            });
        }

        // ═══ Review form ═══
        var $reviews = $('#nr-reviews');
        var postId = $reviews.data('post-id') || 0;
        if (!postId) return;

        var $form = $('#nr-comment-form');
        var $msg = $form.length ? $form.find('.nr-form-message') : $();
        var $rating = $form.length ? $form.find('.nr-stars-edit') : $();
        var $ratingInput = $form.length ? $form.find('input[name="rating"]') : $();
        var $parentInput = $form.length ? $form.find('input[name="comment_parent"]') : $();

        // Emoji picker
        $(document).off('click.nr-emoji-toggle', '.nr-emoji-toggle').on('click.nr-emoji-toggle', '.nr-emoji-toggle', function(e) {
            e.preventDefault();
            $(this).siblings('.nr-emoji-picker').toggle();
        });
        $(document).off('click.nr-emoji', '.nr-emoji').on('click.nr-emoji', '.nr-emoji', function() {
            var emoji = $(this).data('emoji');
            var $ta = $(this).closest('.nr-textarea-wrap').find('textarea');
            var pos = $ta[0].selectionStart || $ta.val().length;
            var val = $ta.val();
            $ta.val(val.slice(0, pos) + emoji + val.slice(pos));
            $ta.focus();
            $ta[0].selectionStart = $ta[0].selectionEnd = pos + emoji.length;
        });

        // Star rating click & form submit — only if logged in (form exists)
        if ($form.length) {
            $rating.off('click.nr').on('click.nr', '.nr-star', function() {
                var v = $(this).data('v');
                $rating.attr('data-rating', v);
                $ratingInput.val(v);
                $rating.find('.nr-star').each(function(i) {
                    $(this).toggleClass('active', i < v);
                });
            });

            $form.off('submit.nr').on('submit.nr', function(e) {
                e.preventDefault();
                $msg.hide();
                var data = {
                    action: 'nr_submit_comment',
                    nonce: nrData.nonce || '',
                    post_id: postId,
                    content: $form.find('[name="content"]').val(),
                    rating: $ratingInput.val() || 0,
                    comment_parent: $parentInput.val() || 0
                };
                $form.find('.nr-submit').prop('disabled', true);
                $.post(nrData.ajax_url, data, null, 'json')
                    .done(function(r) {
                        if (r.success) {
                            $msg.removeClass('error').addClass('success').text(r.data.message).show();
                            $form.find('[name="content"]').val('');
                            $ratingInput.val(0);
                            $rating.attr('data-rating', 0);
                            $rating.find('.nr-star').removeClass('active');
                            $parentInput.val(0);
                            $('.nr-reply-form-wrap.active').removeClass('active');
                        } else {
                            $msg.removeClass('success').addClass('error').text(r.data && r.data.message ? r.data.message : (nrData.i18n ? nrData.i18n.error : 'Error')).show();
                        }
                    })
                    .fail(function() {
                        $msg.removeClass('success').addClass('error').text(nrData.i18n ? nrData.i18n.network_error : 'Network error').show();
                    })
                    .always(function() {
                        $form.find('.nr-submit').prop('disabled', false);
                    });
            });
        }

        // ═══ Reply button ═══
        $(document).off('click.nr-reply', '.nr-reply-btn').on('click.nr-reply', '.nr-reply-btn', function() {
            var commentId = $(this).data('comment-id');
            var $comment = $(this).closest('.nr-comment');

            // Remove any existing reply forms
            $('.nr-reply-form-wrap').remove();

            // Create inline reply form
            var i = nrData.i18n || {};
            var replyHtml = '<div class="nr-reply-form-wrap active">' +
                '<textarea name="reply_content" rows="3" placeholder="' + (i.reply_placeholder || 'Your reply...') + '" required></textarea>' +
                '<p>' +
                '<button type="button" class="nr-submit nr-submit-reply" data-parent-id="' + commentId + '">' + (i.reply || 'Reply') + '</button>' +
                '<button type="button" class="nr-cancel-reply">' + (i.cancel || 'Cancel') + '</button>' +
                '</p>' +
                '<p class="nr-form-message" style="display:none;"></p>' +
                '</div>';
            $comment.after(replyHtml);
        });

        // Cancel reply
        $(document).off('click.nr-cancel', '.nr-cancel-reply').on('click.nr-cancel', '.nr-cancel-reply', function() {
            $(this).closest('.nr-reply-form-wrap').remove();
        });

        // Submit reply
        $(document).off('click.nr-submit-reply', '.nr-submit-reply').on('click.nr-submit-reply', '.nr-submit-reply', function() {
            var $btn = $(this);
            var $wrap = $btn.closest('.nr-reply-form-wrap');
            var $replyMsg = $wrap.find('.nr-form-message');
            var parentId = $btn.data('parent-id');
            var content = $wrap.find('textarea').val();

            if (!content || content.length < 10) {
                $replyMsg.removeClass('success').addClass('error').text(nrData.i18n ? nrData.i18n.reply_min_length : 'Reply must be at least 10 characters.').show();
                return;
            }

            var data = {
                action: 'nr_submit_comment',
                nonce: nrData.nonce || '',
                post_id: postId,
                content: content,
                rating: 0,
                comment_parent: parentId
            };

            $btn.prop('disabled', true);
            $replyMsg.hide();

            $.post(nrData.ajax_url, data, null, 'json')
                .done(function(r) {
                    if (r.success) {
                        $replyMsg.removeClass('error').addClass('success').text(r.data.message).show();
                        $wrap.find('textarea').val('');
                        setTimeout(function() { $wrap.remove(); }, 2000);
                    } else {
                        $replyMsg.removeClass('success').addClass('error').text(r.data && r.data.message ? r.data.message : (nrData.i18n ? nrData.i18n.error : 'Error')).show();
                    }
                })
                .fail(function() {
                    $replyMsg.removeClass('success').addClass('error').text(nrData.i18n ? nrData.i18n.network_error : 'Network error').show();
                })
                .always(function() {
                    $btn.prop('disabled', false);
                });
        });

        // ═══ Load more (pagination) ═══
        $(document).off('click.nr-loadmore', '.nr-load-more').on('click.nr-loadmore', '.nr-load-more', function() {
            var $btn = $(this);
            var currentPage = parseInt($reviews.data('page'), 10) || 1;
            var totalPages = parseInt($reviews.data('total-pages'), 10) || 1;
            var nextPage = currentPage + 1;

            if (nextPage > totalPages) {
                $btn.parent().addClass('nr-hidden');
                return;
            }

            $btn.prop('disabled', true).text(nrData.i18n ? nrData.i18n.loading : 'Loading...');

            $.get(nrData.ajax_url, {
                action: 'nr_load_comments',
                nonce: nrData.load_nonce || '',
                post_id: postId,
                page: nextPage
            }, null, 'json').done(function(r) {
                if (r.success && r.data.html) {
                    $('#nr-comments-list').append(r.data.html);
                    $reviews.data('page', nextPage);
                    if (nextPage >= r.data.total_pages) {
                        $btn.parent().addClass('nr-hidden');
                    }
                }
            }).always(function() {
                $btn.prop('disabled', false).text(nrData.i18n ? nrData.i18n.load_more : 'Load more reviews');
            });
        });

        // ═══ Votes (likes/dislikes) ═══
        $(document).off('click.nr-vote', '.nr-vote').on('click.nr-vote', '.nr-vote', function() {
            var $btn = $(this);
            if ($btn.hasClass('nr-voted')) return;
            var commentId = $btn.data('comment-id');
            var vote = $btn.data('vote');
            $btn.prop('disabled', true);
            $.post(nrData.ajax_url, {
                action: 'nr_vote',
                nonce: nrData.vote_nonce || '',
                comment_id: commentId,
                vote: vote
            }, null, 'json')
                .done(function(r) {
                    if (r.success) {
                        $btn.find('.nr-vote-count').text(r.data.count);
                        $btn.addClass('nr-voted');
                    } else {
                        if (r.data && r.data.message) {
                            $btn.addClass('nr-voted');
                        }
                    }
                })
                .always(function() {
                    $btn.prop('disabled', false);
                });
        });

        // ═══ Social login buttons ═══
        $(document).off('click.nr', '.nr-btn[data-provider]').on('click.nr', '.nr-btn[data-provider]', function() {
            var provider = $(this).data('provider');
            var pid = $(this).data('post-id');
            $.post(nrData.ajax_url, {
                action: 'nr_social_login',
                nonce: nrData.social_nonce || '',
                provider: provider,
                post_id: pid
            }).done(function(r) {
                if (r.success && r.data && r.data.url) {
                    window.location.href = r.data.url;
                } else {
                    alert(r.data && r.data.message ? r.data.message : (nrData.i18n ? nrData.i18n.login_error : 'Login error'));
                }
            });
        });
    });
})(jQuery);
