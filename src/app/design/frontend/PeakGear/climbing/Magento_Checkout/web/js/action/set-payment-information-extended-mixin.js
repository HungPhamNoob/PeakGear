define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/vietnam-region-normalizer'
], function (quote, vietnamRegionNormalizer) {
    'use strict';

    function ensureCheckoutRegions() {
        vietnamRegionNormalizer.ensureAddressRegion(quote.shippingAddress());
        vietnamRegionNormalizer.ensureAddressRegion(quote.billingAddress());
    }

    return function (originalAction) {
        return function (messageContainer, paymentData, skipBilling) {
            ensureCheckoutRegions();

            return originalAction(messageContainer, paymentData, skipBilling);
        };
    };
});
