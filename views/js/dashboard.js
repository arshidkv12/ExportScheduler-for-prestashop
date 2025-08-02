$(function() {

    $('.esr-column_input').each(function() {
        var input = $(this);
        var whitelist = input.data('items') || '[]';

        var tagify = new Tagify(this, {
            whitelist:whitelist,
            enforceWhitelist: true,
            dropdown: {
                enabled: 0,
                maxItems: 50,
                closeOnSelect: false,
            },
        });

        var dragsort = new DragSort(tagify.DOM.scope, {
            selector:'.' + tagify.settings.classNames.tag,
            callbacks: {
                dragEnd: onDragEnd
            }
        })

        function onDragEnd(elm){
            tagify.updateValueByDOMTags()
        }
    });

    if ($('[name="CSVEXPORTSCHEDULER_ENABLE_MAIL"]:checked').val() === '0') {
        $('.esr-to-email').closest('.form-group').hide();
    }
    $('[name="CSVEXPORTSCHEDULER_ENABLE_MAIL"]').on('change', function() {  

        if($('[name="CSVEXPORTSCHEDULER_ENABLE_MAIL"]:checked').val() === '1'){
            $('.esr-to-email').closest('.form-group').show();
        } else { 
            $('.esr-to-email').closest('.form-group').hide();
        }
    })
});
