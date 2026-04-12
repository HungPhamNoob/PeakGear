define([
    'mage/url',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote'
], function (url, fullScreenLoader, quote) {
    'use strict';

    function pickFallbackRegion() {
        var checkoutConfig = window.checkoutConfig || {},
            directoryData = checkoutConfig.directoryData || {},
            countryData = directoryData.VN || {},
            rawRegions = countryData.regions || {},
            firstKey,
            firstRegion,
            id;

        if (Array.isArray(rawRegions) && rawRegions.length) {
            firstRegion = rawRegions[0] || {};
            id = parseInt(firstRegion.id || firstRegion.region_id || firstRegion.value, 10);

            return {
                id: !isNaN(id) && id > 0 ? id : 1,
                name: firstRegion.name || firstRegion.label || 'Hà Nội'
            };
        }

        firstKey = Object.keys(rawRegions)[0];
        if (firstKey) {
            firstRegion = rawRegions[firstKey] || {};
            id = parseInt(firstRegion.id || firstRegion.region_id || firstKey, 10);

            return {
                id: !isNaN(id) && id > 0 ? id : 1,
                name: firstRegion.name || firstRegion.label || 'Hà Nội'
            };
        }

        return {
            id: 1,
            name: 'Hà Nội'
        };
    }

    function ensureShippingRegion() {
        var shippingAddress = quote.shippingAddress(),
            regionId,
            fallbackRegion;

        if (!shippingAddress) {
            return;
        }

        regionId = parseInt(
            shippingAddress.regionId ||
            shippingAddress.region_id ||
            (shippingAddress.region && shippingAddress.region.region_id ? shippingAddress.region.region_id : 0),
            10
        );

        shippingAddress.countryId = 'VN';
        shippingAddress.country_id = 'VN';

        if (!isNaN(regionId) && regionId > 0) {
            shippingAddress.regionId = regionId;
            shippingAddress.region_id = regionId;
            return;
        }

        fallbackRegion = pickFallbackRegion();
        shippingAddress.regionId = fallbackRegion.id;
        shippingAddress.region_id = fallbackRegion.id;

        if (shippingAddress.region && typeof shippingAddress.region === 'object') {
            shippingAddress.region.region = shippingAddress.region.region || fallbackRegion.name;
            shippingAddress.region.region_id = fallbackRegion.id;
        } else {
            shippingAddress.region = shippingAddress.region || fallbackRegion.name;
        }
    }

    return function (originalPlaceOrder) {
        return function (paymentData, messageContainer) {
            var method = (quote.paymentMethod() && quote.paymentMethod().method) || (paymentData && paymentData.method) || '';
            var isOffline = /checkmo|cashondelivery|cash|cod|store|tiền mặt|nhận cửa hàng/i.test(method);

            console.log('%c[PLACE ORDER DEBUG] Method: ' + method + ' | Offline: ' + isOffline, 'color:#e91e63;font-weight:bold');

            ensureShippingRegion();

            if (isOffline) {
                console.log('%c[FORCE OFFLINE] Bypassing remaining actions -> redirect straight to successful-payment', 'color:green;font-weight:bold');
                fullScreenLoader.startLoader();

                var deferred = originalPlaceOrder(paymentData, messageContainer);

                if (deferred && typeof deferred.always === 'function') {
                    deferred.always(function () {
                        fullScreenLoader.stopLoader();
                        window.location.replace(url.build('checkout/successful-payment'));
                    });
                } else if (deferred && typeof deferred.finally === 'function') {
                    deferred.finally(function () {
                        fullScreenLoader.stopLoader();
                        window.location.replace(url.build('checkout/successful-payment'));
                    });
                } else {
                    fullScreenLoader.stopLoader();
                    window.location.replace(url.build('checkout/successful-payment'));
                }

                return deferred;
            }

            // Wallet methods (VNPay/ZaloPay) keep their original flow
            return originalPlaceOrder(paymentData, messageContainer);
        };
    };
});