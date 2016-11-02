define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'payiq',
                component: 'PayIQ/js/view/payment/method-renderer/payiq-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
