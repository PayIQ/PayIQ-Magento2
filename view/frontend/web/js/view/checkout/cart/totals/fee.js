/*jshint browser:true jquery:true*/
/*global alert*/
define(
    [
        'PayIQ_Magento2/js/view/checkout/summary/fee'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'PayIQ_Magento2/checkout/cart/totals/fee'
            },
            /**
             * @override
             */
            isDisplayed: function () {
                return this.getValue(true) > 0;
            }
        });
    }
);
