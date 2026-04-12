define([
    'Magento_Checkout/js/view/payment/default',
    'mage/url'
], function (Component, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Vendor_VNPay/payment/vnpay',
            redirectAfterPlaceOrder: false
        },

        initialize: function () {
            this._super();
            this.isGatewayRedirecting = false;

            return this;
        },

        isGatewayConfigured: function () {
            var paymentConfig = window.checkoutConfig && window.checkoutConfig.payment
                ? window.checkoutConfig.payment
                : {},
                methodConfig = paymentConfig[this.getCode()] || {};

            return !!methodConfig.isConfigured;
        },

        canStartGatewayPayment: function () {
            return this.getCode() === this.isChecked() &&
                this.isGatewayConfigured() &&
                this.isPlaceOrderActionAllowed() &&
                !this.isGatewayRedirecting;
        },

        startGatewayPayment: function (data, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            if (!this.canStartGatewayPayment()) {
                return false;
            }

            this.selectPaymentMethod();
            this.isGatewayRedirecting = true;

            return this.placeOrder();
        },

        afterPlaceOrder: function () {
            window.location.replace(urlBuilder.build('vnpay/payment/redirect'));
        },

        getPlaceOrderDeferredObject: function () {
            var deferred = this._super();

            if (deferred && typeof deferred.fail === 'function') {
                deferred.fail(function () {
                    this.isGatewayRedirecting = false;
                }.bind(this));
            }

            return deferred;
        }
    });
});
