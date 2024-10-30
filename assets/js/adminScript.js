"use strict";
jQuery(function($){
    $('select#jigoshop_paypal_payments_pro_paymentType').change(function(){
        var $PaymentType = $(this).closest('tr');
        var $transactionType = $PaymentType.next('tr');
        var $PaymentMethod = $transactionType.next('tr');
        var $template = $PaymentMethod.next('tr');
        if($(this).val() == "CCP"){
            $PaymentMethod.hide();
            $template.hide();
        } else {
            $PaymentMethod.show();
            $template.show();
        }
    }).change();
});