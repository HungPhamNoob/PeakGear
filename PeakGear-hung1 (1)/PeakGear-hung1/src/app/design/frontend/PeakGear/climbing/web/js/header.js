/**
 * PeakGear Climbing Theme - Header Component
 *
 * @category  PeakGear
 * @package   PeakGear_Climbing
 */

define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Customer/js/customer-data'
], function (Component, ko, $, customerData) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'PeakGear_Climbing/header'
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();
            
            this.isOpen = ko.observable(false);
            this.activeCategory = ko.observable(null);
            this.isMobileMenuOpen = ko.observable(false);
            this.isMobileCategoriesOpen = ko.observable(false);
            this.isDropdownOpen = ko.observable(false);
            
            // Customer data
            this.customer = customerData.get('customer');
            
            // Bind methods
            this.openDropdown = this.openDropdown.bind(this);
            this.closeDropdown = this.closeDropdown.bind(this);
            this.toggleMobileMenu = this.toggleMobileMenu.bind(this);
            this.toggleMobileCategories = this.toggleMobileCategories.bind(this);
            
            // Setup sticky header
            this._setupStickyHeader();
            
            return this;
        },

        /**
         * Check if user is logged in
         * @returns {Boolean}
         */
        isLoggedIn: function () {
            return this.customer() && this.customer().fullname;
        },

        /**
         * Get customer initial
         * @returns {String}
         */
        getCustomerInitial: function () {
            var customer = this.customer();
            if (customer && customer.fullname) {
                return customer.fullname.charAt(0).toUpperCase();
            }
            return '';
        },

        /**
         * Open dropdown menu
         */
        openDropdown: function () {
            this.isOpen(true);
        },

        /**
         * Close dropdown menu
         */
        closeDropdown: function () {
            this.isOpen(false);
            this.activeCategory(null);
        },

        /**
         * Set active category for subcategory panel
         * @param {Object} category
         */
        setActiveCategory: function (category) {
            this.activeCategory(category);
        },

        /**
         * Toggle mobile menu
         */
        toggleMobileMenu: function () {
            this.isMobileMenuOpen(!this.isMobileMenuOpen());
            
            // Prevent body scroll when menu is open
            if (this.isMobileMenuOpen()) {
                $('body').addClass('mobile-menu-active');
            } else {
                $('body').removeClass('mobile-menu-active');
            }
        },

        /**
         * Toggle mobile categories accordion
         */
        toggleMobileCategories: function () {
            this.isMobileCategoriesOpen(!this.isMobileCategoriesOpen());
        },

        /**
         * Open account dropdown
         */
        openAccountDropdown: function () {
            this.isDropdownOpen(true);
        },

        /**
         * Close account dropdown
         */
        closeAccountDropdown: function () {
            this.isDropdownOpen(false);
        },

        /**
         * Setup sticky header behavior
         * @private
         */
        _setupStickyHeader: function () {
            var self = this,
                header = $('#peakgear-header'),
                lastScrollTop = 0,
                delta = 5,
                headerHeight = header.outerHeight();

            $(window).on('scroll', function () {
                var scrollTop = $(this).scrollTop();

                // Make sure scroll is more than delta
                if (Math.abs(lastScrollTop - scrollTop) <= delta) {
                    return;
                }

                // If scrolled down and past header
                if (scrollTop > lastScrollTop && scrollTop > headerHeight) {
                    // Scroll Down - hide header
                    header.addClass('header-hidden');
                } else {
                    // Scroll Up - show header
                    header.removeClass('header-hidden');
                    
                    // Add background when scrolled
                    if (scrollTop > 100) {
                        header.addClass('header-scrolled');
                    } else {
                        header.removeClass('header-scrolled');
                    }
                }

                lastScrollTop = scrollTop;
            });
        }
    });
});
