/**
 * 
 */
$(function() {
// override jquery validate plugin defaults
$.validator.setDefaults({
    highlight: function(element) {
        $(element).closest('.form-group').addClass('has-error');
        $(element).siblings('label:first').addClass('control-label');
    },
    unhighlight: function(element) {
        $(element).closest('.form-group').removeClass('has-error');
        $(element).siblings('label:first').removeClass('control-label');
    },
    errorElement: 'span',
    errorClass: 'help-block',
    errorPlacement: function(error, element) {
        if(element.parent('.input-group').length) {
            error.insertAfter(element.parent());
        } else {
            error.insertAfter(element);
        }
    }
});
$.validator.addMethod('phone', function (value) {
    return /^(|\+[0-9\- \(\)]{7,29})(?: ext\. \d{1,4})?$/.test(value); 
}, 'Please begin with \'+\' and the country code, and use only numbers, dash, space or parenthesis. \' ext. ##\' may be added for extensions.');

$("#edit_person").validate({
    rules : {
    	cellPhone : { required: false, phone : true },
    	phone1 : { required: false, phone : true },
    	phone2 : { required: false, phone : true },
    	phone3 : { required: false, phone : true },
    	emergencyContact1Phone : { required: false, phone : true },
    	emergencyContact2Phone : { required: false, phone : true },
    }
});
});