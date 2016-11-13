/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'PayIQ_Magento2/js/action/set-payment-method',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data'
    ],
    function (ko, $, Component, setPaymentMethodAction, selectPaymentMethodAction, quote, checkoutData) {
        'use strict';

        return Component.extend({
            defaults: {
                self: this,
                template: 'PayIQ_Magento2/payment/payiq'
            },
            /** Redirect to PayIQ */
            continueToPayIQ: function () {
                //update payment method information if additional data was changed
                console.log(this.selectPaymentMethod());
                console.log(setPaymentMethodAction());
                this.selectPaymentMethod();
                setPaymentMethodAction();
                return false;
            }
        });
    }
);
