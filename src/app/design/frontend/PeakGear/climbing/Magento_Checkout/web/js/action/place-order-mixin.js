define([
    'jquery',
    'mage/url',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/model/vietnam-region-normalizer'
], function ($, url, fullScreenLoader, quote, setShippingInformationAction, vietnamRegionNormalizer) {
    'use strict';

    function ensureCheckoutRegions() {
        vietnamRegionNormalizer.ensureAddressRegion(quote.shippingAddress());
        vietnamRegionNormalizer.ensureAddressRegion(quote.billingAddress());
    }

    function placeOrderAfterShippingSync(originalPlaceOrder, paymentData, messageContainer) {
        var shippingAddress = quote.shippingAddress(),
            shippingMethod = quote.shippingMethod();

        ensureCheckoutRegions();

        if (!shippingAddress || !shippingMethod) {
            return originalPlaceOrder(paymentData, messageContainer);
        }

        return $.when(setShippingInformationAction()).then(function () {
            ensureCheckoutRegions();

            return originalPlaceOrder(paymentData, messageContainer);
        });
    }

    return function (originalPlaceOrder) {
        return function (paymentData, messageContainer) {
            var method = (quote.paymentMethod() && quote.paymentMethod().method) || (paymentData && paymentData.method) || '';
            var isOffline = /checkmo|cashondelivery|cash|cod|store|tiền mặt|nhận cửa hàng/i.test(method);

            console.log('%c[PLACE ORDER DEBUG] Method: ' + method + ' | Offline: ' + isOffline, 'color:#e91e63;font-weight:bold');

            if (isOffline) {
                console.log('%c[FORCE OFFLINE] Bypassing remaining actions -> redirect straight to successful-payment', 'color:green;font-weight:bold');
                fullScreenLoader.startLoader();

                var deferred = placeOrderAfterShippingSync(originalPlaceOrder, paymentData, messageContainer);

                if (deferred && typeof deferred.done === 'function') {
                    deferred.done(function () {
                        fullScreenLoader.stopLoader();
                        window.location.replace(url.build('checkout/successful-payment'));
                    }).fail(function () {
                        fullScreenLoader.stopLoader();
                    });
                } else {
                    fullScreenLoader.stopLoader();
                    window.location.replace(url.build('checkout/successful-payment'));
                }

                return deferred;
            }

            // Wallet methods (VNPay/ZaloPay) keep their original flow
            return placeOrderAfterShippingSync(originalPlaceOrder, paymentData, messageContainer);
        };
    };
});
