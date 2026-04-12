define([
    'Magento_Checkout/js/model/checkout-method-resolver'
], function (resolveCheckoutMethod) {
    'use strict';

    return function (resourceUrlManager) {
        resourceUrlManager.getCheckoutMethod = function () {
            return resolveCheckoutMethod();
        };

        return resourceUrlManager;
    };
});