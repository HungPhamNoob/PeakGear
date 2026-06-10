define([
    'jquery',
    'mage/translate',
    'mage/validation'
], function ($, $t) {
    'use strict';

    function normalize(value) {
        var raw = (value || '').toString().trim(),
            digits = raw.replace(/\D+/g, '');

        if ((raw.indexOf('+') === 0 && digits.indexOf('84') === 0) ||
            (digits.indexOf('84') === 0 && digits.length >= 11)) {
            return '0' + digits.slice(2);
        }

        return digits;
    }

    function isValid(value) {
        return /^0[1-9][0-9]{8,9}$/.test(normalize(value));
    }

    function register() {
        if (!$.validator || $.validator.methods['validate-vietnam-phone']) {
            return;
        }

        $.validator.addMethod(
            'validate-vietnam-phone',
            function (value) {
                return $.mage.isEmptyNoTrim(value) || isValid(value);
            },
            $t('Vui lòng nhập số điện thoại hợp lệ (VD: 0912345678).')
        );
    }

    function initialize() {
        register();
    }

    initialize.normalize = normalize;
    initialize.isValid = isValid;
    initialize.register = register;

    register();

    return initialize;
});
