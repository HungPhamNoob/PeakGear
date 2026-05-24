define(['mage/utils/wrapper'], function (wrapper) {
    'use strict';

    return function (priceUtils) {
        priceUtils.formatPrice = wrapper.wrap(priceUtils.formatPrice, function (originalFormatPrice, amount, format, isShowSign) {
            var formattedPrice = originalFormatPrice(amount, format, isShowSign);
            
            // Check if formatted string contains VND currency symbol '₫' or 'VND'
            if (formattedPrice.indexOf('₫') !== -1 || formattedPrice.indexOf('VND') !== -1) {
                // Remove trailing '.00' and any trailing spaces/zeroes
                formattedPrice = formattedPrice.replace(/\.00(?=[^\d]|$)/g, '');
                
                // If the symbol is at the beginning, move it to the end
                if (formattedPrice.trim().indexOf('₫') === 0) {
                    formattedPrice = formattedPrice.replace('₫', '').trim() + '₫';
                } else if (formattedPrice.trim().indexOf('VND') === 0) {
                    formattedPrice = formattedPrice.replace('VND', '').trim() + ' ₫';
                }
            }

            return formattedPrice;
        });

        return priceUtils;
    };
});
