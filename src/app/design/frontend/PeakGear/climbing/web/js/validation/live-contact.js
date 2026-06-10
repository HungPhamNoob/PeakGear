define([
    'jquery',
    'js/validation/vietnam-phone',
    'mage/validation'
], function ($, vietnamPhone) {
    'use strict';

    var selector = [
            '[data-validate*="validate-email"]',
            '[data-validate*="validate-vietnam-phone"]',
            '[data-validate*="validate-login-identifier"]'
        ].join(','),
        eventNamespace = '.peakgearLiveContactValidation';

    function validateField(element) {
        var $element = $(element);

        if (!$element.is(':visible') || $element.is(':disabled') || !element.form) {
            return;
        }

        $element.valid();
    }

    function initialize() {
        vietnamPhone.register();

        $(document)
            .off('input' + eventNamespace + ' blur' + eventNamespace, selector)
            .on('input' + eventNamespace + ' blur' + eventNamespace, selector, function () {
                validateField(this);
            });
    }

    return initialize;
});
