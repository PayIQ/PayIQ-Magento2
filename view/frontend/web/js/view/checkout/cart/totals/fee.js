/*jshint browser:true jquery:true*/
/*global alert*/
define(
    [
        'PayIQ/js/view/checkout/summary/fee'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'PayIQ/checkout/cart/totals/fee'
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
