define([
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer'
], function (quote, customer) {
    'use strict';

    function hasGuestEmail() {
        return typeof quote.guestEmail === 'string' && quote.guestEmail.length > 0;
    }

    return function () {
        var checkoutConfig = window.checkoutConfig || {},
            customerFlag = typeof checkoutConfig.isCustomerLoggedIn === 'boolean'
                ? checkoutConfig.isCustomerLoggedIn
                : null,
            isCustomerLoggedIn = customer.isLoggedIn(),
            quoteData = checkoutConfig.quoteData || {};

        // If checkout already has guest email, this quote must use guest APIs.
        if (hasGuestEmail()) {
            return 'guest';
        }

        // Explicitly logged-out config/state must always use guest APIs.
        if (customerFlag === false || isCustomerLoggedIn === false) {
            return 'guest';
        }

        // Logged-in customers should only use customer APIs with owned quote.
        if (customerFlag === true || isCustomerLoggedIn === true) {
            return quoteData && quoteData.customer_id ? 'customer' : 'guest';
        }

        return 'guest';
    };
});