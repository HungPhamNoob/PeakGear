define([
    'ko',
    'Magento_Checkout/js/model/quote'
], function (ko, quote) {
    'use strict';

    function normalizeCode(code) {
        return (code || '').toLowerCase();
    }

    function isWalletMethod(code) {
        var normalizedCode = normalizeCode(code);

        return normalizedCode.indexOf('zalo') !== -1 || normalizedCode.indexOf('vnpay') !== -1;
    }

    function isOfflineMethod(code) {
        var normalizedCode = normalizeCode(code);

        return normalizedCode === 'checkmo' ||
            normalizedCode === 'cashondelivery' ||
            normalizedCode.indexOf('store') !== -1 ||
            normalizedCode.indexOf('cash') !== -1 ||
            normalizedCode.indexOf('cod') !== -1;
    }

    function readPaymentConfig(methodCode) {
        var payment = window.checkoutConfig && window.checkoutConfig.payment ? window.checkoutConfig.payment : {},
            methodConfig = methodCode && payment[methodCode] ? payment[methodCode] : {};

        return methodConfig;
    }

    return function (Payment) {
        return Payment.extend({
            initialize: function () {
                var self = this;

                this._super();

                this.selectedPaymentCode = ko.observable('');
                this.homeDeliveryMode = ko.observable(true);
                this.bodyClassObserver = null;
                this.paymentMethodsObserver = null;
                this.hasForcedOfflineDefault = false;

                this.syncSelectedPaymentCode();
                this.syncHomeDeliveryMode();
                this.observeBodyClassChanges();
                this.observePaymentMethods();
                this.ensureOfflineDefaultSelection();

                quote.paymentMethod.subscribe(function () {
                    self.syncSelectedPaymentCode();
                    self.ensureOfflineDefaultSelection();
                });
                window.addEventListener('hashchange', function () {
                    if (!self.isPaymentStep()) {
                        self.hasForcedOfflineDefault = false;
                    }

                    self.syncHomeDeliveryMode();
                    self.ensureOfflineDefaultSelection();
                });
                quote.shippingAddress.subscribe(this.syncHomeDeliveryMode.bind(this));

                return this;
            },

            isPaymentStep: function () {
                return window.location.hash === '#payment';
            },

            observeBodyClassChanges: function () {
                var self = this;

                if (!window.MutationObserver || !document.body) {
                    return;
                }

                this.bodyClassObserver = new MutationObserver(function () {
                    self.syncHomeDeliveryMode();
                });

                this.bodyClassObserver.observe(document.body, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            },

            syncSelectedPaymentCode: function () {
                var paymentMethod = quote.paymentMethod(),
                    checkedCode = this.getCheckedPaymentCode(),
                    code = (paymentMethod && paymentMethod.method ? paymentMethod.method : '') || checkedCode;

                this.selectedPaymentCode(code);
            },

            getCheckedPaymentCode: function () {
                var checkedRadio = document.querySelector('#co-payment-form input[name="payment[method]"]:checked');

                return checkedRadio ? (checkedRadio.value || '') : '';
            },

            findPreferredOfflineCode: function () {
                var radios = document.querySelectorAll('#co-payment-form input[name="payment[method]"]'),
                    preferredOrder = ['cashondelivery', 'checkmo'],
                    radioValues = [],
                    i,
                    j,
                    candidate;

                Array.prototype.forEach.call(radios, function (radio) {
                    if (radio && radio.value) {
                        radioValues.push(radio.value);
                    }
                });

                for (i = 0; i < preferredOrder.length; i += 1) {
                    for (j = 0; j < radioValues.length; j += 1) {
                        candidate = radioValues[j];
                        if (normalizeCode(candidate) === preferredOrder[i]) {
                            return candidate;
                        }
                    }
                }

                for (i = 0; i < radioValues.length; i += 1) {
                    candidate = radioValues[i];

                    if (isOfflineMethod(candidate) && !isWalletMethod(candidate)) {
                        return candidate;
                    }
                }

                return '';
            },

            selectPaymentCodeInDom: function (code) {
                var radios = document.querySelectorAll('#co-payment-form input[name="payment[method]"]'),
                    targetRadio = null;

                if (!code) {
                    return;
                }

                Array.prototype.forEach.call(radios, function (radio) {
                    if (!targetRadio && radio && radio.value === code) {
                        targetRadio = radio;
                    }
                });

                if (!targetRadio) {
                    return;
                }

                if (!targetRadio.checked) {
                    targetRadio.checked = true;
                }

                if (typeof targetRadio.click === 'function') {
                    targetRadio.click();
                }

                targetRadio.dispatchEvent(new Event('change', { bubbles: true }));
            },

            ensureOfflineDefaultSelection: function () {
                var activeCode,
                    preferredOfflineCode;

                if (!this.isPaymentStep() || this.hasForcedOfflineDefault) {
                    return;
                }

                activeCode = this.getCheckedPaymentCode() || this.selectedPaymentCode();
                preferredOfflineCode = this.findPreferredOfflineCode();

                if (!preferredOfflineCode) {
                    return;
                }

                if (!activeCode || isWalletMethod(activeCode) || !isOfflineMethod(activeCode)) {
                    this.selectPaymentCodeInDom(preferredOfflineCode);
                    this.selectedPaymentCode(preferredOfflineCode);
                }

                this.hasForcedOfflineDefault = true;
            },

            observePaymentMethods: function () {
                var self = this,
                    attempts = 0,
                    maxAttempts = 30,
                    attachObserver = function () {
                        var paymentMethodsHost = document.getElementById('checkout-payment-method-load');

                        if (!window.MutationObserver) {
                            return;
                        }

                        if (!paymentMethodsHost) {
                            if (attempts < maxAttempts) {
                                attempts += 1;
                                window.setTimeout(attachObserver, 120);
                            }
                            return;
                        }

                        self.paymentMethodsObserver = new MutationObserver(function () {
                            self.ensureOfflineDefaultSelection();
                        });

                        self.paymentMethodsObserver.observe(paymentMethodsHost, {
                            childList: true,
                            subtree: true
                        });

                        self.ensureOfflineDefaultSelection();
                    };

                attachObserver();
            },

            syncHomeDeliveryMode: function () {
                var classes = document.body && document.body.classList ? document.body.classList : null;

                this.homeDeliveryMode(!(classes && classes.contains('pg-delivery-store')));
            },

            isHomeDeliveryMode: function () {
                return this.homeDeliveryMode();
            },

            isQrPaymentSelected: function () {
                return isWalletMethod(this.selectedPaymentCode());
            },

            qrImageUrl: function () {
                var code = this.selectedPaymentCode(),
                    methodConfig = readPaymentConfig(code);

                if (methodConfig.qrImageUrl) {
                    return methodConfig.qrImageUrl;
                }

                if (methodConfig.qr_image_url) {
                    return methodConfig.qr_image_url;
                }

                return '';
            },

            hasQrImage: function () {
                return !!this.qrImageUrl();
            },

            shippingAddressLines: function () {
                var address = quote.shippingAddress(),
                    lines = [],
                    fullName = '',
                    street = [],
                    locality = '';

                if (!address) {
                    return lines;
                }

                fullName = [address.firstname, address.middlename, address.lastname].filter(Boolean).join(' ').trim();
                if (fullName) {
                    lines.push(fullName);
                }

                if (Array.isArray(address.street)) {
                    street = address.street.filter(Boolean);
                } else if (address.street) {
                    street = [address.street];
                }
                if (street.length) {
                    lines.push(street.join(', '));
                }

                locality = [address.city, address.region, address.postcode].filter(Boolean).join(', ').trim();
                if (locality) {
                    lines.push(locality);
                }

                if (address.countryId) {
                    lines.push(address.countryId === 'VN' ? 'Việt Nam' : address.countryId);
                }

                if (address.telephone) {
                    lines.push('SĐT: ' + address.telephone);
                }

                return lines;
            }
        });
    };
});