(function($) {
    if (typeof nrEditorStatus === 'undefined' || !nrEditorStatus.ajax_url) return;
    var i = nrEditorStatus.i18n || {};

    $.post(nrEditorStatus.ajax_url, { action: 'nr_editor_status', nonce: nrEditorStatus.nonce || '' }, null, 'json')
        .done(function(r) {
            if (!r || !r.logged_in || !r.can_edit) return;
            var bar = document.createElement('div');
            bar.className = 'nr-editor-status-bar';
            var nameSpan = document.createElement('strong');
            nameSpan.textContent = r.name || (i.editor || 'editor');
            var textSpan = document.createElement('span');
            textSpan.className = 'nr-editor-status-bar__text';
            textSpan.appendChild(document.createTextNode((i.logged_as || 'Logged in as') + ' '));
            textSpan.appendChild(nameSpan);
            textSpan.appendChild(document.createTextNode('. '));
            var logoutLink = document.createElement('a');
            logoutLink.href = r.logout_url || '#';
            logoutLink.className = 'nr-editor-status-bar__logout';
            logoutLink.textContent = i.logout || 'Log out';
            bar.appendChild(textSpan);
            bar.appendChild(logoutLink);
            document.body.appendChild(bar);
            document.body.classList.add('nr-has-editor-status-bar');
        });
})(jQuery);
