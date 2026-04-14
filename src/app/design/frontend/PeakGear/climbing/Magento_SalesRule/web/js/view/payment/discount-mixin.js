define([
    'jquery',
    'Magento_Ui/js/model/messageList',
    'mage/translate'
], function ($, messageList, $t) {
    'use strict';

    var INVALID_COUPON_TEXT = "The coupon code isn't valid. Verify the code and try again.",
        INVALID_COUPON_TEXT_VN = 'Mã giảm giá không hợp lệ. Vui lòng kiểm tra và thử lại.',
        APPLY_SUCCESS_TEXT_VN = 'Áp dụng mã giảm giá thành công.';

    function stripHtml(text) {
        return (text || '').toString().replace(/<[^>]*>/g, '').trim();
    }

    function normalizeErrorMessage(message) {
        var text = stripHtml(message),
            normalized = text.toLowerCase();

        if (text.indexOf(INVALID_COUPON_TEXT) !== -1 ||
            normalized.indexOf('coupon code') !== -1 && normalized.indexOf('not valid') !== -1) {
            return INVALID_COUPON_TEXT_VN;
        }

        if (normalized.indexOf('coupon code is empty') !== -1 ||
            normalized.indexOf('please enter a coupon code') !== -1) {
            return 'Vui lòng nhập mã giảm giá.';
        }

        if (normalized.indexOf('coupon code has expired') !== -1 ||
            normalized.indexOf('coupon has expired') !== -1) {
            return 'Mã giảm giá đã hết hạn.';
        }

        return text;
    }

    function normalizeSuccessMessage(message, couponCode) {
        var text = (message || '').toString(),
            normalizedCoupon = (couponCode || '').toString().trim().toLowerCase();

        if (/you used coupon code/i.test(text) || /coupon code .* is applied/i.test(text)) {
            return APPLY_SUCCESS_TEXT_VN;
        }

        if (normalizedCoupon && /coupon code/i.test(text)) {
            return APPLY_SUCCESS_TEXT_VN;
        }

        return text;
    }

    function wrapMessageList() {
        var originalError,
            originalSuccess;

        if (messageList.__peakgearCouponWrapped) {
            return;
        }

        originalError = messageList.addErrorMessage.bind(messageList);
        originalSuccess = messageList.addSuccessMessage.bind(messageList);

        messageList.addErrorMessage = function (messageObject) {
            var payload = $.extend({}, messageObject || {});

            payload.message = normalizeErrorMessage(payload.message);

            return originalError(payload);
        };

        messageList.addSuccessMessage = function (messageObject) {
            var payload = $.extend({}, messageObject || {}),
                activeCoupon = window.peakgearLastCouponCode || '';

            payload.message = normalizeSuccessMessage(payload.message, activeCoupon);

            return originalSuccess(payload);
        };

        messageList.__peakgearCouponWrapped = true;
    }

    return function (Component) {
        return Component.extend({
            initialize: function () {
                this._super();
                wrapMessageList();
                this.isApplied(!!(this.couponCode() || '').trim());

                return this;
            },

            apply: function () {
                var code = (this.couponCode() || '').trim();

                this.couponCode(code);
                window.peakgearLastCouponCode = code.toLowerCase();

                return this._super();
            },

            validate: function () {
                var code = (this.couponCode() || '').trim();

                this.couponCode(code);

                if (!code) {
                    messageList.addErrorMessage({
                        message: $t('Mã giảm giá không hợp lệ. Vui lòng kiểm tra và thử lại.')
                    });

                    return false;
                }

                return this._super();
            }
        });
    };
});
