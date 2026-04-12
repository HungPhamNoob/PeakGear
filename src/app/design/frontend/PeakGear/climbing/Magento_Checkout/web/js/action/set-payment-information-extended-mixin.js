define([
    'Magento_Checkout/js/model/quote'
], function (quote) {
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

    return function (originalAction) {
        return function (messageContainer, paymentData, skipBilling) {
            ensureShippingRegion();

            return originalAction(messageContainer, paymentData, skipBilling);
        };
    };
});