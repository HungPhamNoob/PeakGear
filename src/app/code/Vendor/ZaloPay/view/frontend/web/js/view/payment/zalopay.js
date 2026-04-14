define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'zalopay',
        component: 'Vendor_ZaloPay/js/view/payment/method-renderer/zalopay-method'
    });

    return Component.extend({});
});
