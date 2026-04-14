define([
    'Magento_Checkout/js/model/quote'
], function (quote) {
    'use strict';

    function normalizeCode(code) {
        return (code || '').toLowerCase();
    }

    function isOfflineMethod(code) {
        var normalizedCode = normalizeCode(code);

        return normalizedCode === 'checkmo' ||
            normalizedCode === 'cashondelivery' ||
            normalizedCode.indexOf('store') !== -1 ||
            normalizedCode.indexOf('cash') !== -1 ||
            normalizedCode.indexOf('cod') !== -1;
    }

    return function (Component) {
        return Component.extend({
            placeOrder: function (data, event) {
                var currentMethod = (quote.paymentMethod() && quote.paymentMethod().method) || this.getCode() || '',
                    deferred,
                    self = this;

                if (!isOfflineMethod(currentMethod)) {
                    return this._super(data, event);
                }

                console.log('[PAYMENT DEFAULT DEBUG] Bypass validate for offline method:', currentMethod);

                if (event) {
                    event.preventDefault();
                }

                if (!this.isPlaceOrderActionAllowed()) {
                    return false;
                }

                this.isPlaceOrderActionAllowed(false);
                deferred = this.getPlaceOrderDeferredObject();

                if (deferred && typeof deferred.always === 'function') {
                    deferred.always(function () {
                        self.isPlaceOrderActionAllowed(true);
                    });
                } else {
                    self.isPlaceOrderActionAllowed(true);
                }

                return false;
            }
        });
    };
});