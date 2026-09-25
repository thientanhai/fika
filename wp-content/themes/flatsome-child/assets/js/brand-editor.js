/* Sync only Fika's taxonomy editor; no storefront JavaScript. */
(function ($) {
    'use strict';

    function syncEditor(form) {
        if (!form || !window.tinymce) return;
        ['fika_brand_bottom_add', 'fika_brand_bottom_edit'].forEach(function (id) {
            var field = document.getElementById(id);
            var editor = window.tinymce.get(id);
            if (field && form.contains(field) && editor && !editor.isHidden()) editor.save();
        });
    }

    // Capture runs before WordPress's bubbling click serializes #addtag.
    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('#addtag #submit');
        if (button) syncEditor(button.form);
    }, true);
    document.addEventListener('submit', function (event) {
        if (event.target.id === 'addtag' || event.target.id === 'edittag') syncEditor(event.target);
    }, true);

    $(document).ajaxSuccess(function (event, xhr, settings) {
        var data = typeof settings.data === 'string' ? new URLSearchParams(settings.data) : null;
        if (!data || data.get('action') !== 'add-tag' || data.get('taxonomy') !== 'product_brand' || !data.has('fika_brand_bottom_nonce')) return;
        var xml = xhr.responseXML;
        if (!xml || xml.getElementsByTagName('wp_error').length) return;
        var added = Array.prototype.some.call(xml.getElementsByTagName('term'), function (term) {
            var id = term.getElementsByTagName('term_id')[0];
            return id && Number(id.textContent) > 0;
        });
        if (!added) return;
        var field = document.getElementById('fika_brand_bottom_add');
        var editor = window.tinymce && window.tinymce.get('fika_brand_bottom_add');
        if (field) field.value = '';
        if (editor) {
            editor.setContent('');
            editor.save();
            editor.setDirty(false);
        }
    });
}(jQuery));
