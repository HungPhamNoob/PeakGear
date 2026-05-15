/**
 * PeakGear product card quick actions.
 */
define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/cookies',
    'mage/translate'
], function ($, customerData, cookies, $t) {
    'use strict';

    var initialized = false;

    function getWishlistItemsByProduct(data) {
        var itemsByProduct = {};

        if (!data || !data.items || !data.items.length) {
            return itemsByProduct;
        }

        data.items.forEach(function (item) {
            var productId = (item.product_id || item.product || '').toString(),
                removeUrl = '',
                itemId = item.item_id || item.wishlist_item_id || '';

            if (!productId) {
                return;
            }

            if (item.delete_item_params) {
                try {
                    var params = JSON.parse(item.delete_item_params);
                    removeUrl = params.action || '';
                    if (params.data && params.data.item) {
                        itemId = params.data.item;
                    }
                } catch (e) {
                    removeUrl = '';
                }
            }

            itemsByProduct[productId] = {
                itemId: itemId,
                removeUrl: removeUrl
            };
        });

        return itemsByProduct;
    }

    function updateWishlistIcons(data) {
        var wishlistItems = getWishlistItemsByProduct(data);

        $('.product-action-wishlist, .action-wishlist').each(function () {
            var $button = $(this),
                productId = ($button.data('product-id') || '').toString(),
                item = productId ? wishlistItems[productId] : null;

            $button
                .toggleClass('is-wishlisted added', !!item)
                .attr('aria-pressed', item ? 'true' : 'false')
                .data('wishlist-dynamic-item', item ? item.itemId : '')
                .data('wishlist-dynamic-remove-url', item ? item.removeUrl : '');

            $button.find('svg').attr('fill', item ? 'currentColor' : 'none');
        });
    }

    function isCustomerLoggedIn() {
        var customer = customerData.get('customer')();

        return !!(customer && (customer.firstname || customer.fullname || customer.email));
    }

    function showNotification(message, type) {
        var notification = $('<div/>', {
            'class': 'peakgear-notification notification-' + type,
            'text': message
        });

        $('body').append(notification);

        setTimeout(function () {
            notification.addClass('show');
        }, 10);

        setTimeout(function () {
            notification.removeClass('show');
            setTimeout(function () {
                notification.remove();
            }, 300);
        }, 3000);
    }

    function showTopMessage(message, type) {
        var $placeholder = $('[data-placeholder="messages"]').first(),
            messageClass = type === 'error' ? 'message-error error' : 'message-success success',
            $messages,
            $message;

        if (!$placeholder.length) {
            showNotification(message, type);
            return;
        }

        $messages = $placeholder.children('.messages');
        if (!$messages.length) {
            $messages = $('<div class="messages"></div>');
            $placeholder.append($messages);
        }

        $message = $('<div/>', {
            'class': 'message ' + messageClass
        }).append($('<div/>').text(message));

        $messages.append($message);

        setTimeout(function () {
            $message.addClass('toast-dismiss');
            setTimeout(function () {
                $message.remove();
            }, 350);
        }, 3500);
    }

    function showWishlistLoginModal(loginUrl, registerUrl) {
        var $modal = $('#peakgear-wishlist-login-modal');

        if (!$modal.length) {
            $modal = $(
                    '<div id="peakgear-wishlist-login-modal" class="pg-wishlist-login-modal" role="dialog" aria-modal="true" aria-labelledby="pg-wishlist-login-title">' +
                        '<div class="pg-wishlist-login-backdrop" data-role="close"></div>' +
                        '<div class="pg-wishlist-login-panel">' +
                            '<button type="button" class="pg-wishlist-login-close" data-role="close" aria-label="' + $t('Đóng') + '">×</button>' +
                            '<div class="pg-wishlist-cat-graphic" aria-hidden="true">' +
                                '<svg viewBox="0 0 240 150" role="img" focusable="false">' +
                                    '<path class="pg-yarn-thread" d="M88 102 C116 82 136 126 166 98 C184 82 198 91 206 104" />' +
                                    '<g class="pg-cat-tail"><path d="M75 91 C43 80 48 40 76 52 C94 60 86 82 71 75" /></g>' +
                                    '<g class="pg-cat-body">' +
                                        '<ellipse class="pg-cat-body-fill" cx="104" cy="90" rx="44" ry="31" />' +
                                        '<circle class="pg-cat-head-fill" cx="83" cy="63" r="28" />' +
                                        '<path class="pg-cat-head-fill" d="M63 46 L60 20 L81 39 Z" />' +
                                        '<path class="pg-cat-head-fill" d="M93 38 L113 20 L108 49 Z" />' +
                                        '<path class="pg-cat-ear-inner" d="M66 40 L64 28 L75 38 Z" />' +
                                        '<path class="pg-cat-ear-inner" d="M96 37 L107 28 L104 43 Z" />' +
                                        '<circle class="pg-cat-eye" cx="74" cy="60" r="3.5" />' +
                                        '<circle class="pg-cat-eye" cx="93" cy="60" r="3.5" />' +
                                        '<path class="pg-cat-mouth" d="M82 68 Q86 72 90 68" />' +
                                        '<path class="pg-cat-mouth" d="M82 68 Q78 72 74 68" />' +
                                        '<circle class="pg-cat-nose" cx="82" cy="66" r="2.6" />' +
                                        '<path class="pg-cat-whisker" d="M66 64 L48 59 M66 69 L47 70 M98 64 L116 59 M98 69 L117 70" />' +
                                        '<path class="pg-cat-paw pg-cat-paw-left" d="M117 103 C130 92 142 92 151 103" />' +
                                        '<path class="pg-cat-paw pg-cat-paw-right" d="M93 111 C103 118 115 118 126 109" />' +
                                    '</g>' +
                                    '<g class="pg-heart-yarn">' +
                                        '<path class="pg-heart-fill" d="M178 113 C147 89 132 70 146 54 C158 40 175 50 178 63 C181 50 198 40 210 54 C224 70 209 89 178 113 Z" />' +
                                        '<path class="pg-yarn-line" d="M151 65 C166 55 187 56 205 66" />' +
                                        '<path class="pg-yarn-line" d="M146 78 C164 68 194 69 211 82" />' +
                                        '<path class="pg-yarn-line" d="M155 94 C170 84 190 85 203 95" />' +
                                    '</g>' +
                                    '<g class="pg-floating-hearts">' +
                                        '<path d="M44 39 C37 34 34 29 38 25 C41 22 45 24 46 28 C47 24 51 22 54 25 C58 29 55 34 48 39 Z" />' +
                                        '<path d="M196 34 C190 30 188 25 191 22 C194 19 198 21 199 24 C200 21 204 19 207 22 C210 25 208 30 202 34 Z" />' +
                                    '</g>' +
                                '</svg>' +
                            '</div>' +
                            '<h3 id="pg-wishlist-login-title">' + $t('Hãy đăng nhập để thêm vào yêu thích') + '</h3>' +
                            '<p>' + $t('Đăng nhập hoặc tạo tài khoản để lưu sản phẩm vào danh sách yêu thích của bạn.') + '</p>' +
                        '<div class="pg-wishlist-login-actions">' +
                            '<a class="pg-wishlist-login-primary" data-role="login" href="#">' + $t('Đăng nhập') + '</a>' +
                            '<a class="pg-wishlist-login-secondary" data-role="register" href="#">' + $t('Đăng ký') + '</a>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
            $('body').append($modal);
            $modal.on('click', '[data-role="close"]', function () {
                $modal.removeClass('is-open');
            });
        }

        $modal.find('[data-role="login"]').attr('href', loginUrl || '/customer/account/login');
        $modal.find('[data-role="register"]').attr('href', registerUrl || '/customer/account/create');
        $modal.addClass('is-open');
    }

    function toggleWishlist($button) {
        var productId = $button.data('product-id'),
            formKey = $('input[name="form_key"]').first().val() || $.mage.cookies.get('form_key'),
            loginUrl = $button.data('login-url'),
            registerUrl = $button.data('register-url'),
            inWishlist = $button.hasClass('is-wishlisted') || $button.hasClass('added'),
            endpoint = inWishlist
                ? ($button.data('wishlist-dynamic-remove-url') || $button.data('wishlist-remove-url'))
                : $button.data('wishlist-url'),
            formData = new FormData(),
            itemId = $button.data('wishlist-dynamic-item');

        if (!productId || !endpoint || $button.prop('disabled')) {
            return;
        }

        if (!inWishlist && !isCustomerLoggedIn()) {
            showWishlistLoginModal(loginUrl, registerUrl);
            return;
        }

        if (inWishlist && itemId) {
            formData.append('item', itemId);
        } else {
            formData.append('product', productId);
        }

        formData.append('form_key', formKey);
        formData.append('uenc', window.btoa(window.location.href));

        $button.prop('disabled', true);

        fetch(endpoint, {
            method: 'POST',
            body: formData,
            redirect: 'follow',
            credentials: 'same-origin'
        })
            .then(function (response) {
                var finalUrl = response.url || '';

                if (finalUrl.indexOf('customer/account/login') !== -1) {
                    showWishlistLoginModal(loginUrl, registerUrl);
                    return;
                }

                customerData.invalidate(['wishlist']);
                return customerData.reload(['wishlist'], true);
            })
            .catch(function () {
                showTopMessage($t('Có lỗi xảy ra, thử lại.'), 'error');
            })
            .finally(function () {
                $button.prop('disabled', false);
            });
    }

    function init() {
        var wishlist = customerData.get('wishlist');

        if (initialized) {
            updateWishlistIcons(wishlist());
            return;
        }

        initialized = true;

        wishlist.subscribe(updateWishlistIcons);
        updateWishlistIcons(wishlist());

        $(document)
            .off('click.peakgearWishlist', '.product-action-wishlist')
            .on('click.peakgearWishlist', '.product-action-wishlist', function (event) {
                event.preventDefault();
                event.stopPropagation();
                toggleWishlist($(this));
            });

        $(document)
            .off('ajax:addToCart.peakgearProductActions')
            .on('ajax:addToCart.peakgearProductActions', function (event, data) {
                if (data && data.response && !data.response.error) {
                    showTopMessage($t('Đã thêm sản phẩm vào giỏ hàng.'), 'success');
                    customerData.invalidate(['cart']);
                    customerData.reload(['cart'], true);
                }
            });
    }

    return function () {
        init();
    };
});
