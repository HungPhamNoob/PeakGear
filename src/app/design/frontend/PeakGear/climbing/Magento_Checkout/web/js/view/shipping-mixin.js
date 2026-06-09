define([
    'jquery',
    'ko',
    'uiRegistry',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/vietnam-region-normalizer',
    'mage/translate',
    'mage/validation'
], function ($, ko, registry, quote, vietnamRegionNormalizer, $t) {
    'use strict';

    var STORE_MODE_CLASS = 'pg-delivery-store',
        STORE_PICKUP_PAYMENT_CLASS = 'pg-store-pickup-payment';

    function bodyClassList() {
        return document.body && document.body.classList ? document.body.classList : null;
    }

    function isPaymentStep() {
        return window.location.hash === '#payment';
    }

    function normalizeVietnamPhone(value) {
        var raw = (value || '').toString().trim(),
            digits = raw.replace(/\D+/g, '');

        if ((raw.indexOf('+') === 0 && digits.indexOf('84') === 0) || digits.indexOf('84') === 0) {
            digits = '0' + digits.slice(2);
        }

        return digits;
    }

    function isValidVietnamPhone(value) {
        return /^0[0-9]{9,10}$/.test(normalizeVietnamPhone(value));
    }

    function registerVietnamPhoneValidator() {
        if (!$.validator || $.validator.methods['validate-vietnam-phone']) {
            return;
        }

        $.validator.addMethod(
            'validate-vietnam-phone',
            function (value) {
                return $.mage.isEmptyNoTrim(value) || isValidVietnamPhone(value);
            },
            $t('Vui lòng nhập số điện thoại hợp lệ (VD: 0912345678).')
        );
    }

    function normalizeVietnamName(value) {
        return vietnamRegionNormalizer.normalizeVietnamName(value);
    }

    function toUniqueCityOptions(cities) {
        var seen = {},
            options = [];

        options.push({
            value: '',
            label: 'Chọn Thành phố'
        });

        cities.forEach(function (city) {
            var name = (city || '').toString().trim();

            if (!name || seen[name.toLowerCase()]) {
                return;
            }

            seen[name.toLowerCase()] = true;
            options.push({
                value: name,
                label: name
            });
        });

        return options;
    }

    function fallbackVietnamCities() {
        return [
            'An Giang', 'Bà Rịa - Vũng Tàu', 'Bắc Giang', 'Bắc Kạn', 'Bạc Liêu',
            'Bắc Ninh', 'Bến Tre', 'Bình Định', 'Bình Dương', 'Bình Phước',
            'Bình Thuận', 'Cà Mau', 'Cần Thơ', 'Cao Bằng', 'Đà Nẵng',
            'Đắk Lắk', 'Đắk Nông', 'Điện Biên', 'Đồng Nai', 'Đồng Tháp',
            'Gia Lai', 'Hà Giang', 'Hà Nam', 'Hà Nội', 'Hà Tĩnh',
            'Hải Dương', 'Hải Phòng', 'Hậu Giang', 'Hòa Bình', 'Hưng Yên',
            'Khánh Hòa', 'Kiên Giang', 'Kon Tum', 'Lai Châu', 'Lâm Đồng',
            'Lạng Sơn', 'Lào Cai', 'Long An', 'Nam Định', 'Nghệ An',
            'Ninh Bình', 'Ninh Thuận', 'Phú Thọ', 'Phú Yên', 'Quảng Bình',
            'Quảng Nam', 'Quảng Ngãi', 'Quảng Ninh', 'Quảng Trị', 'Sóc Trăng',
            'Sơn La', 'Tây Ninh', 'Thái Bình', 'Thái Nguyên', 'Thanh Hóa',
            'Thừa Thiên Huế', 'Tiền Giang', 'TP. Hồ Chí Minh', 'Trà Vinh',
            'Tuyên Quang', 'Vĩnh Long', 'Vĩnh Phúc', 'Yên Bái'
        ];
    }

    function extractVietnamRegions() {
        return vietnamRegionNormalizer.extractVietnamRegions();
    }

    function toCityOptionsFromRegions(regions) {
        var cities = (regions || []).map(function (region) {
            return region && region.name ? region.name : '';
        });

        return toUniqueCityOptions(cities.length ? cities : fallbackVietnamCities());
    }

    return function (Shipping) {
        registerVietnamPhoneValidator();

        return Shipping.extend({
            initialize: function () {
                this._super();

                this.deliveryMode = ko.observable('home');
                this.cityField = null;
                this.regionField = null;
                this.regionIdField = null;
                this.vietnamRegions = extractVietnamRegions();
                this.vietnamRegionsByName = {};
                this.vietnamFallbackRegion = null;
                this.indexVietnamRegions(this.vietnamRegions);
                this.showShippingMethodList = ko.pureComputed(function () {
                    return this.deliveryMode() !== 'store';
                }, this);

                this.deliveryMode.subscribe(function (mode) {
                    var rates = this.rates(),
                        selectedMethod = quote.shippingMethod();

                    this.updateCheckoutStateClasses();

                    if (mode !== 'store') {
                        return;
                    }

                    // Keep checkout flow valid when rates UI is hidden for store pickup.
                    if (!selectedMethod && rates.length && typeof this.selectShippingMethod === 'function') {
                        this.selectShippingMethod(rates[0]);
                    }
                }, this);

                quote.shippingAddress.subscribe(function (address) {
                    if (!address) {
                        return;
                    }

                    this.syncRegionByCity(address.city || '');
                    this.ensureQuoteRegionConsistency();
                }, this);

                this.configureVietnamAddressFields();
                this.initializeCheckoutStateClasses();
                this.ensureQuoteRegionConsistency();

                return this;
            },

            validateShippingInformation: function () {
                var isValid;

                if (!quote.shippingAddress()) {
                    this.errorValidationMessage($t('Vui lòng nhập địa chỉ giao hàng.'));
                    return false;
                }

                isValid = this._super();

                if (!isValid && this.isFormInline && this.source && this.source.get && this.source.get('params.invalid')) {
                    this.errorValidationMessage($t('Vui lòng nhập địa chỉ giao hàng.'));
                }

                return isValid;
            },

            initializeCheckoutStateClasses: function () {
                var self = this;

                this.updateCheckoutStateClasses();

                window.addEventListener('hashchange', function () {
                    self.updateCheckoutStateClasses();
                });
            },

            updateCheckoutStateClasses: function () {
                var classes = bodyClassList(),
                    isStore = this.deliveryMode && this.deliveryMode() === 'store',
                    isStorePickupPayment = isStore && isPaymentStep();

                if (!classes) {
                    return;
                }

                classes.toggle(STORE_MODE_CLASS, isStore);
                classes.toggle(STORE_PICKUP_PAYMENT_CLASS, isStorePickupPayment);
            },

            configureVietnamAddressFields: function () {
                var self = this,
                    countryPath = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.country_id',
                    cityPath = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.city',
                    regionPath = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.region',
                    regionIdPath = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.region_id',
                    telephonePath = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.telephone',
                    streetLine2Path = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.street.1';

                registry.async(countryPath)(function (countryField) {
                    if (countryField && typeof countryField.visible === 'function') {
                        countryField.visible(false);
                    }

                    if (countryField && typeof countryField.value === 'function') {
                        countryField.value('VN');
                    }

                    if (self.source && typeof self.source.set === 'function') {
                        self.source.set('shippingAddress.country_id', 'VN');
                    }
                });

                registry.async(cityPath)(function (cityField) {
                    var cityOptions = toCityOptionsFromRegions(self.vietnamRegions);

                    self.cityField = cityField;

                    if (cityField && typeof cityField.value === 'function' && cityField.value.subscribe) {
                        cityField.value.subscribe(function (cityValue) {
                            self.syncRegionByCity(cityValue);
                        });
                    }

                    self.applyCityOptions(cityField, cityOptions);
                    self.syncRegionByCity(cityField && typeof cityField.value === 'function' ? cityField.value() : '');
                });

                registry.async(regionPath)(function (regionField) {
                    self.regionField = regionField;

                    if (regionField && typeof regionField.visible === 'function') {
                        regionField.visible(false);
                    }

                    self.syncRegionByCity();
                });

                registry.async(telephonePath)(function (telephoneField) {
                    if (!telephoneField || typeof telephoneField.value !== 'function' || !telephoneField.value.subscribe) {
                        return;
                    }

                    telephoneField.value.subscribe(function (rawValue) {
                        var sanitized = normalizeVietnamPhone(rawValue);

                        if (sanitized !== rawValue) {
                            telephoneField.value(sanitized);

                            if (self.source && typeof self.source.set === 'function') {
                                self.source.set('shippingAddress.telephone', sanitized);
                            }
                        }
                    });
                });

                registry.async(regionIdPath)(function (regionIdField) {
                    self.regionIdField = regionIdField;

                    if (regionIdField && typeof regionIdField.visible === 'function') {
                        regionIdField.visible(false);
                    }

                    self.syncRegionByCity();
                });

                registry.async(streetLine2Path)(function (streetLine2Field) {
                    if (streetLine2Field && typeof streetLine2Field.visible === 'function') {
                        streetLine2Field.visible(false);
                    }

                    if (streetLine2Field && typeof streetLine2Field.disabled === 'function') {
                        streetLine2Field.disabled(true);
                    }

                    if (streetLine2Field && typeof streetLine2Field.value === 'function') {
                        streetLine2Field.value('');
                    }

                    if (self.source && typeof self.source.set === 'function') {
                        self.source.set('shippingAddress.street.1', '');
                    }
                });

                // Keep the default region text field out of the shipping form.
                window.setTimeout(function () {
                    var regionInputs = document.querySelectorAll(
                        '#shipping-new-address-form input[name="region"], ' +
                        '#shipping-new-address-form input[name="shippingAddress.region"], ' +
                        '#shipping-new-address-form .field[name="shippingAddress.region"]'
                    );

                    Array.prototype.forEach.call(regionInputs, function (element) {
                        var field = element.closest ? element.closest('.field') : null;

                        if (field) {
                            field.remove();
                        } else if (element.remove) {
                            element.remove();
                        }
                    });
                }, 0);
            },

            indexVietnamRegions: function (regions) {
                var self = this;

                (regions || []).forEach(function (region) {
                    var normalizedName,
                        normalizedCode;

                    if (!region || !region.id || !region.name) {
                        return;
                    }

                    normalizedName = normalizeVietnamName(region.name);
                    normalizedCode = normalizeVietnamName(region.code || '');

                    if (normalizedName && !self.vietnamRegionsByName[normalizedName]) {
                        self.vietnamRegionsByName[normalizedName] = region;
                    }

                    if (normalizedCode && !self.vietnamRegionsByName[normalizedCode]) {
                        self.vietnamRegionsByName[normalizedCode] = region;
                    }

                    if (!self.vietnamFallbackRegion) {
                        self.vietnamFallbackRegion = region;
                    }
                });
            },

            resolveRegionByCity: function (cityValue) {
                var normalizedCity = normalizeVietnamName(cityValue);

                if (!normalizedCity) {
                    return null;
                }

                return this.vietnamRegionsByName[normalizedCity] || null;
            },

            applyRegionSelection: function (region) {
                var shippingAddress = quote.shippingAddress();

                if (!region) {
                    return;
                }

                if (this.regionIdField && typeof this.regionIdField.value === 'function') {
                    this.regionIdField.value(String(region.id));
                }

                if (this.regionField && typeof this.regionField.value === 'function') {
                    this.regionField.value(region.name);
                }

                if (this.source && typeof this.source.set === 'function') {
                    this.source.set('shippingAddress.region_id', region.id);
                    this.source.set('shippingAddress.regionId', region.id);
                    this.source.set('shippingAddress.region', region.name);
                }

                if (shippingAddress) {
                    shippingAddress.countryId = 'VN';
                    shippingAddress.country_id = 'VN';
                    shippingAddress.regionId = region.id;
                    shippingAddress.region_id = region.id;

                    if (shippingAddress.region && typeof shippingAddress.region === 'object') {
                        shippingAddress.region.region = region.name;
                        shippingAddress.region.region_id = region.id;
                    } else {
                        shippingAddress.region = region.name;
                    }
                }
            },

            resolveRegionFromAddress: function (address) {
                return vietnamRegionNormalizer.resolveAddressRegion(address);
            },

            ensureQuoteRegionConsistency: function () {
                var address = quote.shippingAddress(),
                    region = null;

                if (!address) {
                    return;
                }

                region = this.resolveRegionFromAddress(address) ||
                    this.resolveRegionByCity(address.city || '') ||
                    this.vietnamFallbackRegion;

                this.applyRegionSelection(region);
            },

            syncRegionByCity: function (cityValue) {
                var currentCity = cityValue,
                    resolvedRegion;

                if (typeof currentCity === 'undefined' && this.cityField && typeof this.cityField.value === 'function') {
                    currentCity = this.cityField.value();
                }

                resolvedRegion = this.resolveRegionByCity(currentCity) ||
                    this.resolveRegionFromAddress(quote.shippingAddress()) ||
                    this.vietnamFallbackRegion;
                this.applyRegionSelection(resolvedRegion);
            },

            applyCityOptions: function (cityField, options) {
                if (!cityField || !options || !options.length) {
                    return;
                }

                if (typeof cityField.setOptions === 'function') {
                    cityField.setOptions(options);
                }

                if (typeof cityField.options === 'function') {
                    cityField.options(options);
                }
            },

            loadCitiesFromWeather: function (cityField, fallback) {
                var self = this;

                if (!window.fetch) {
                    return;
                }

                window.fetch('/weather/index/data', {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Failed to load city list');
                    }

                    return response.json();
                }).then(function (payload) {
                    var cities = [],
                        options;

                    if (payload && Array.isArray(payload.cities)) {
                        cities = payload.cities.map(function (city) {
                            return city && city.city_name ? city.city_name : '';
                        });
                    } else if (Array.isArray(payload)) {
                        cities = payload.map(function (city) {
                            return city && city.city_name ? city.city_name : '';
                        });
                    }

                    options = toUniqueCityOptions(fallbackVietnamCities().concat(cities));
                    if (options.length <= 1) {
                        options = fallback;
                    }

                    self.applyCityOptions(cityField, options);
                    self.syncRegionByCity(cityField && typeof cityField.value === 'function' ? cityField.value() : '');
                }).catch(function () {
                    self.applyCityOptions(cityField, fallback);
                    self.syncRegionByCity(cityField && typeof cityField.value === 'function' ? cityField.value() : '');
                });
            }
        });
    };
});
