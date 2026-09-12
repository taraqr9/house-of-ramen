(function (global) {
    var defaultToolbar = [
        ['bold', 'italic', 'underline', 'strike'],
        [{list: 'ordered'}, {list: 'bullet'}],
        ['link'],
        ['clean'],
    ];

    function initRichTextEditor(wrapper, options) {
        options = options || {};

        var $wrapper = $(wrapper);

        if ($wrapper.data('quill-initialized')) {
            return null;
        }

        var editorEl = $wrapper.find('.rich-text-editor').get(0);
        var hiddenInput = $wrapper.find('.rich-text-input').get(0);

        if (!editorEl || !hiddenInput) {
            return null;
        }

        var quill = new Quill(editorEl, {
            theme: 'snow',
            placeholder: options.placeholder || '',
            modules: {
                toolbar: options.toolbar || defaultToolbar,
            },
        });

        hiddenInput.value = quill.root.innerHTML;

        quill.on('text-change', function () {
            hiddenInput.value = quill.root.innerHTML;
        });

        $wrapper.data('quill-initialized', true);

        return quill;
    }

    function initAllRichTextEditors(context) {
        $(context || document).find('.rich-text-wrapper').each(function () {
            initRichTextEditor(this);
        });
    }

    global.PmeRichText = {
        init: initRichTextEditor,
        initAll: initAllRichTextEditors,
    };
})(window);
