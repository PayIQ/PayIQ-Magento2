/*jshint browser:true jquery:true*/
/*global alert*/
define(
    [
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Checkout/js/model/quote',
        'Magento_Catalog/js/price-utils',
        'Magento_Checkout/js/model/totals'
    ],
    function (Component, quote, priceUtils, totals) {
        'use strict';
        var displayMode = window.checkoutConfig.payiqPaymentFee.cartDisplayMode;

        return Component.extend({
            defaults: {
                displayMode: displayMode,
                template: 'PayIQ/checkout/summary/fee'
            },
            totals: quote.getTotals(),
            isDisplayed: function () {
                return this.getValue(true) > 0;
            },
            isBothPricesDisplayed: function() {
                return 'both' == this.displayMode;
            },
            isIncludingTaxDisplayed: function() {
                return 'including' == this.displayMode;
            },
            getValue: function (noformat) {
                var price = 0;
                if (this.totals()) {
                    price = totals.getSegment('payiq_payment_fee').value;
                }
                return noformat ? price : this.getFormattedPrice(price);
            },
            getValueInclTax: function() {
                var price = 0;
                if (this.totals()) {
                    price = totals.getSegment('payiq_payment_fee').value + totals.getSegment('payiq_payment_fee_tax').value;
                }
                return this.getFormattedPrice(price);
            },
            getBaseValue: function() {
                var basePrice = 0;
                if (this.totals()) {
                    basePrice = totals.getSegment('payiq_payment_fee').value;
                }
                return priceUtils.formatPrice(basePrice, quote.getBasePriceFormat());
            }
        });
    }
);
