/*jshint browser:true jquery:true*/
/*global alert*/
var config = {
    config: {
        //mixins: {
        //    'PayIQ/js/action/place-order': {
        //        'Magento_CheckoutAgreements/js/model/place-order-mixin': true
        //    }
        //}
    },
    map: {
        '*': {
            'Magento_Checkout/js/action/select-payment-method':
                'PayIQ/js/action/select-payment-method'
        }
    }
};

