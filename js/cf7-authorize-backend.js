(function($){
    $(document).ready(function(){
        // disable all dropdowns if ignored
        $('input#ignore-form').on('change',function(){
            if ($(this).attr('checked')) {
                $('input[type="radio"][name*="cf7-authorize"], select[name*="cf7-authorize"], button.cf7-authorize-add-custom-field').attr('disabled', true).trigger('chosen:updated');
            } else {
                $('input[type="radio"][name*="cf7-authorize"], select[name*="cf7-authorize"], button.cf7-authorize-add-custom-field').attr('disabled', false).trigger('chosen:updated');
            }
        });

        // re-enable just prior to form submission
        $('form#wpcf7-admin-form-element').on('submit', function(){
            $('select[name*="cf7-authorize"]').attr('disabled', false);
        });

        // add chosen.js
        $('select[name*="cf7-authorize"]:not([name*="custom-field-template-name"])').chosen({
            width: '100%',
            placeholder_text_multiple: 'Select some options or leave blank to ignore'
        });

        // add message to Authorize.net tab if main content is changed
        $('#wpcf7-form').on('change', function(){
            $('.cf7-authorize-message').html('It looks like you&rsquo;ve changed the form content; please save the form before changing any Authorize.net settings.');
            $('select[name*="cf7-authorize"]').attr('disabled', true);
            $('.cf7-authorize-table').hide();
        });

        // add ability to clone new custom fields
        $('.cf7-authorize-add-custom-field').on('click', function(event) {
            event.preventDefault();
            $('.cf7-authorize-field-custom-field-template').clone(true).attr('class', 'cf7-authorize-field-custom').addClass('new').appendTo('.cf7-authorize-table tbody').find('select').chosen({width: '100%'});
        });

        // set name of new custom fields
        $('.cf7-authorize-table').on('blur', '.cf7-authorize-field-custom.new input[name="custom-field-name"]', function() {
            var $parent = $(this).parents('.cf7-authorize-field-custom.new'),
                customFieldName = $(this).val().length > -1 ? $(this).val() : 'custom-field-name';

            $parent.find('select').attr('name', 'cf7-authorize[fields][' + customFieldName + '][]').trigger('chosen:updated');
            $parent.find('code span.name').html(customFieldName);
        });
    });
})(jQuery);
