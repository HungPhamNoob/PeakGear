define([
    'ko',
    'Magento_Checkout/js/model/quote'
], function (ko, quote) {
    'use strict';

    var PAYMENT_STEP_CLASS = 'pg-payment-step';

    function classList() {
        return document.body && document.body.classList ? document.body.classList : null;
    }

    function setPaymentStepClass(isActive) {
        var classes = classList();

        if (!classes) {
            return;
        }

        classes.toggle(PAYMENT_STEP_CLASS, !!isActive);
    }

    function isPaymentHash() {
        return window.location.hash === '#payment';
    }

    function isWalletMethod(code) {
        var normalizedCode = (code || '').toLowerCase();

        return normalizedCode.indexOf('vnpay') !== -1 || normalizedCode.indexOf('zalo') !== -1;
    }

    function isOfflineMethod(code) {
        var normalizedCode = (code || '').toLowerCase();

        return normalizedCode === 'checkmo' ||
            normalizedCode === 'cashondelivery' ||
            normalizedCode.indexOf('store') !== -1 ||
            normalizedCode.indexOf('cash') !== -1 ||
            normalizedCode.indexOf('cod') !== -1;
    }

    function getCheckedPaymentCode() {
        var checkedRadio = document.querySelector('#co-payment-form input[name="payment[method]"]:checked');

        return checkedRadio ? (checkedRadio.value || '') : '';
    }

    function findPreferredOfflineCode() {
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
                if ((candidate || '').toLowerCase() === preferredOrder[i]) {
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
    }

    function resolveActivePaymentCode(viewModel) {
        var checkedCode = getCheckedPaymentCode(),
            preferredOfflineCode = findPreferredOfflineCode(),
            selectedCode = viewModel && typeof viewModel.selectedPaymentCode === 'function'
                ? viewModel.selectedPaymentCode()
                : '';

        return checkedCode || preferredOfflineCode || selectedCode || '';
    }

    function applyVndPriceFormat() {
        var checkoutConfig = window.checkoutConfig || {},
            priceFormat = checkoutConfig.priceFormat || null;

        if (!priceFormat) {
            return;
        }

        priceFormat.pattern = '%s VNĐ';
        priceFormat.precision = 0;
        priceFormat.requiredPrecision = 0;
        priceFormat.decimalSymbol = ',';
        priceFormat.groupSymbol = '.';
        priceFormat.groupLength = 3;
    }

    function normalizeCurrencyText(text) {
        if (!text || text.indexOf('$') === -1 && text.indexOf('USD') === -1) {
            return text;
        }

        return text
            .replace(/-\$\s*([0-9][0-9.,]*)/g, '-$1 VNĐ')
            .replace(/\$\s*([0-9][0-9.,]*)/g, '$1 VNĐ')
            .replace(/\bUSD\b/g, 'VNĐ')
            .replace(/VNĐ\s*VNĐ/g, 'VNĐ');
    }

    function normalizeCurrencyDom(rootNode) {
        var walker,
            currentNode,
            updatedText;

        if (!rootNode || !window.document || !document.createTreeWalker || !window.NodeFilter) {
            return;
        }

        walker = document.createTreeWalker(rootNode, NodeFilter.SHOW_TEXT, null);
        currentNode = walker.nextNode();

        while (currentNode) {
            updatedText = normalizeCurrencyText(currentNode.nodeValue);
            if (updatedText !== currentNode.nodeValue) {
                currentNode.nodeValue = updatedText;
            }
            currentNode = walker.nextNode();
        }
    }

    function normalizeProgressLabel() {
        var progressLabels = document.querySelectorAll('.opc-progress-bar-item > span');

        if (!progressLabels.length) {
            return;
        }

        if (progressLabels[0].textContent !== 'Vận chuyển') {
            progressLabels[0].textContent = 'Vận chuyển';
        }
    }

    function removeNoActiveCartMessage() {
        var nodes = document.querySelectorAll('.page.messages .message, .pg-checkout-toast-host .message, .messages .message, .ewallet-message');

        Array.prototype.forEach.call(nodes, function (node) {
            var text = (node.textContent || '').toLowerCase();

            if (text.indexOf('current customer does not have an active cart') !== -1 ||
                (text.indexOf('active cart') !== -1 && text.indexOf('current customer') !== -1) ||
                text.indexOf('vui lòng sử dụng nút thanh toán') !== -1 ||
                text.indexOf('ví điện tử đã chọn') !== -1) {
                node.style.display = 'none';
                node.remove();
            }
        });
    }

    return function (Summary) {
        return Summary.extend({
            initialize: function () {
                var self = this;

                this._super();
                this.isPaymentStepActive = ko.observable(false);
                this.selectedPaymentCode = ko.observable('');
                this.currencyObserver = null;
                this.currencyObserverHost = null;
                this.currencySyncTimer = null;
                this.isSyncingDisplay = false;
                this.isSubmittingFromSummary = false;

                this.syncPaymentStepState();
                this.syncSelectedPaymentCode();
                this.syncVndDisplay();
                this.observeCurrencyChanges();
                window.addEventListener('hashchange', function () {
                    self.syncPaymentStepState();
                });
                document.addEventListener('change', function (event) {
                    if (event && event.target && event.target.name === 'payment[method]') {
                        self.syncSelectedPaymentCode();
                    }
                });
                quote.paymentMethod.subscribe(function () {
                    self.syncSelectedPaymentCode();
                });

                return this;
            },

            syncPaymentStepState: function () {
                var active = isPaymentHash();

                this.isPaymentStepActive(active);
                setPaymentStepClass(active);
            },

            syncSelectedPaymentCode: function () {
                var paymentMethod = quote.paymentMethod(),
                    checkedCode = getCheckedPaymentCode(),
                    code = checkedCode || (paymentMethod && paymentMethod.method ? paymentMethod.method : '');

                this.selectedPaymentCode(code);
            },

            syncVndDisplay: function () {
                if (this.isSyncingDisplay) {
                    return;
                }

                this.isSyncingDisplay = true;
                this.pauseCurrencyObserver();

                try {
                    applyVndPriceFormat();
                    normalizeCurrencyDom(document.querySelector('.checkout-maincontent') || document.body);
                    normalizeProgressLabel();
                    removeNoActiveCartMessage();
                } finally {
                    this.resumeCurrencyObserver();
                    this.isSyncingDisplay = false;
                }
            },

            scheduleVndDisplaySync: function () {
                var self = this;

                if (this.currencySyncTimer) {
                    return;
                }

                this.currencySyncTimer = window.setTimeout(function () {
                    self.currencySyncTimer = null;
                    self.syncVndDisplay();
                }, 80);
            },

            pauseCurrencyObserver: function () {
                if (this.currencyObserver) {
                    this.currencyObserver.disconnect();
                }
            },

            resumeCurrencyObserver: function () {
                if (!this.currencyObserver || !this.currencyObserverHost) {
                    return;
                }

                this.currencyObserver.observe(this.currencyObserverHost, {
                    childList: true,
                    subtree: true,
                    characterData: true
                });
            },

            observeCurrencyChanges: function () {
                var self = this,
                    host = document.querySelector('.checkout-maincontent') || document.body;

                if (!window.MutationObserver || !host) {
                    return;
                }

                this.currencyObserverHost = host;

                this.currencyObserver = new MutationObserver(function () {
                    self.scheduleVndDisplaySync();
                });

                this.resumeCurrencyObserver();
            },

            isPlaceOrderFooterVisible: function () {
                return this.isPaymentStepActive && this.isPaymentStepActive();
            },

            isGenericPlaceOrderAllowed: function () {
                var activeCode = resolveActivePaymentCode(this);

                if (!activeCode) {
                    return true;
                }

                if (isOfflineMethod(activeCode)) {
                    return true;
                }

                return !isWalletMethod(activeCode);
            },

            placeOrderFromSummary: function (data, event) {
                var activeButton,
                    self = this;

                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                if (this.isSubmittingFromSummary) {
                    return false;
                }

                if (!this.isGenericPlaceOrderAllowed()) {
                    console.log('[SUMMARY DEBUG] Place order blocked for wallet method');
                    return false;
                }

                activeButton = document.querySelector('#co-payment-form .payment-method._active button.action.primary.checkout:not([disabled])') ||
                    document.querySelector('#co-payment-form button.action.primary.checkout:not([disabled])');

                if (!activeButton) {
                    console.warn('[SUMMARY DEBUG] No active Magento place-order button found in payment form');
                    return false;
                }

                // Guard against fast double-click from the summary footer.
                this.isSubmittingFromSummary = true;
                window.setTimeout(function () {
                    self.isSubmittingFromSummary = false;
                }, 1200);

                activeButton.click();
                console.log('[SUMMARY DEBUG] Triggered active payment place-order button click');

                return false;
            }
        });
    };
});