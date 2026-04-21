/**
 * PeakGear Climbing Theme - Product List Component
 *
 * @category  PeakGear
 * @package   PeakGear_Climbing
 */

define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Catalog/js/price-utils',
    'mage/translate'
], function (Component, ko, $, priceUtils, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'PeakGear_Climbing/product-list'
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();
            
            // View mode
            this.viewMode = ko.observable('grid');
            
            // Bind methods
            this.setViewMode = this.setViewMode.bind(this);
            
            // Setup wishlist observer
            this._initWishlist();
            
            // Setup product card animations
            this._setupAnimations();
            
            return this;
        },

        /**
         * Set view mode (grid/list)
         * @param {String} mode
         */
        setViewMode: function (mode) {
            this.viewMode(mode);
            
            var container = $('.products-grid');
            container.removeClass('mode-grid mode-list').addClass('mode-' + mode);
        },

        /**
         * Initialize wishlist customer data observer
         * @private
         */
        _initWishlist: function () {
            require(['Magento_Customer/js/customer-data'], function (customerData) {
                var wishlist = customerData.get('wishlist');
                
                var updateWishlistIcons = function (data) {
                    if (data && data.items) {
                        var inWishlistIds = data.items.map(function (item) {
                            // product_id could be undefined, check product_id or product
                            return (item.product_id || item.product || '').toString();
                        });
                        
                        $('.action-wishlist').each(function () {
                            var pid = $(this).data('product-id');
                            if (pid && inWishlistIds.indexOf(pid.toString()) !== -1) {
                                $(this).addClass('added');
                            } else {
                                $(this).removeClass('added');
                            }
                        });
                    }
                };
                
                wishlist.subscribe(updateWishlistIcons);
                updateWishlistIcons(wishlist());
            });
        },

        /**
         * Setup product card animations on scroll
         * @private
         */
        _setupAnimations: function () {
            var self = this;
            
            // Intersection Observer for scroll animations
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animate-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                });

                // Observe all product cards
                setTimeout(function () {
                    document.querySelectorAll('.product-card').forEach(function (card) {
                        observer.observe(card);
                    });
                }, 100);
            }
        },

        /**
         * Show notification message
         * @param {String} message
         * @param {String} type
         * @private
         */
        _showNotification: function (message, type) {
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
    });
});
