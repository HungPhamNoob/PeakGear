define([], function () {
    'use strict';

    return function (redirectOnSuccessAction) {
        redirectOnSuccessAction.redirectUrl = 'checkout/successful-payment';

        return redirectOnSuccessAction;
    };
});