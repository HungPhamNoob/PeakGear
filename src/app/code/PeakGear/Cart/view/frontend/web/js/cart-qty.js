define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/get-totals'
], function ($, customerData, getTotalsAction) {
    'use strict';

    var SAVE_DELAY = 450;

    function parseNumber(value) {
        var number = parseFloat(value);

        return isNaN(number) ? null : number;
    }

    function getPrecision(step) {
        var stringValue = String(step || ''),
            dotIndex = stringValue.indexOf('.');

        return dotIndex === -1 ? 0 : stringValue.length - dotIndex - 1;
    }

    function normalizeQty(value, step) {
        var precision = getPrecision(step);

        if (precision > 0) {
            return parseFloat(value.toFixed(precision));
        }

        return Math.round(value);
    }

    function parseHtml(html) {
        return $('<div>').append($.parseHTML(html, document, true));
    }

    return function (config, element) {
        var $row = $(element),
            $input = $row.find('[data-role="cart-item-qty"]').first(),
            $increase = $row.find('[data-role="qty-action"][data-direction="increase"]').first(),
            $decrease = $row.find('[data-role="qty-action"][data-direction="decrease"]').first(),
            $form = $row.closest('form'),
            rowId = String($row.data('cartItemId') || ''),
            lastCommittedQty = null,
            saveTimer = null,
            inFlight = false,
            retrySync = false,
            changeVersion = 0,
            responseVersion = 0;

        if (!$input.length || $row.data('peakgearCartQtyInit')) {
            return;
        }

        $row.data('peakgearCartQtyInit', true);

        function getMin() {
            var min = parseNumber($input.attr('min'));

            return min === null ? 1 : min;
        }

        function getMax() {
            var max = parseNumber($input.attr('max'));

            return max === null || max < 0 ? null : max;
        }

        function getStep() {
            var step = parseNumber($input.attr('data-qty-step'));

            return step && step > 0 ? step : 1;
        }

        function getQty() {
            var qty = parseNumber($input.val());

            if (qty === null) {
                qty = lastCommittedQty === null ? getMin() : lastCommittedQty;
            }

            return normalizeQty(qty, getStep());
        }

        function setQty(qty) {
            $input.val(normalizeQty(qty, getStep()));
        }

        function clampQty(qty) {
            var min = getMin(),
                max = getMax(),
                normalizedQty = normalizeQty(qty, getStep());

            if (normalizedQty < min) {
                normalizedQty = min;
            }

            if (max !== null && normalizedQty > max) {
                normalizedQty = max;
            }

            return normalizedQty;
        }

        function refreshState() {
            var qty = getQty(),
                min = getMin(),
                max = getMax(),
                step = getStep();

            $decrease.prop('disabled', qty - step < min);

            if (max === null) {
                $increase.prop('disabled', false);
                return;
            }

            $increase.prop('disabled', qty >= max || qty + step > max);
        }

        function syncInputMeta(qty) {
            $input.attr('data-item-qty', qty);
        }

        function applySnapshot(html, version) {
            var $snapshot = parseHtml(html),
                $sourceRow = rowId ? $snapshot.find('[data-cart-item-id="' + rowId + '"]').first() : $(),
                $sourceInput = $sourceRow.find('[data-role="cart-item-qty"]').first(),
                serverQty = null;

            if (version !== changeVersion || !$sourceRow.length) {
                return;
            }

            if ($sourceInput.length) {
                serverQty = clampQty(parseNumber($sourceInput.val()) === null ? getQty() : parseNumber($sourceInput.val()));
                lastCommittedQty = serverQty;
                syncInputMeta(serverQty);
            }

            if ($sourceInput.length && serverQty !== null) {
                setQty(serverQty);
            }

            if ($sourceRow.find('.col.price').length) {
                $row.find('.col.price').first().html($sourceRow.find('.col.price').first().html());
            }

            if ($sourceRow.find('.col.subtotal').length) {
                $row.find('.col.subtotal').first().html($sourceRow.find('.col.subtotal').first().html());
            }

            ['data-qty-min', 'data-qty-max', 'data-qty-step'].forEach(function (attributeName) {
                var attributeValue = $sourceRow.attr(attributeName);

                if (typeof attributeValue === 'undefined') {
                    return;
                }

                if (attributeValue === '') {
                    $row.removeAttr(attributeName);
                } else {
                    $row.attr(attributeName, attributeValue);
                }
            });

            ['min', 'max', 'data-qty-min', 'data-qty-max', 'data-qty-step'].forEach(function (attributeName) {
                var attributeValue = $sourceInput.attr(attributeName);

                if (typeof attributeValue === 'undefined') {
                    return;
                }

                if (attributeValue === '') {
                    $input.removeAttr(attributeName);
                } else {
                    $input.attr(attributeName, attributeValue);
                }
            });

            if ($sourceInput.length && serverQty !== null) {
                syncInputMeta(serverQty);
            } else {
                syncInputMeta(getQty());
            }

            refreshState();

            getTotalsAction([]);
            customerData.reload(['cart'], false);
        }

        function revertToCommittedQty() {
            if (lastCommittedQty === null) {
                return;
            }

            setQty(lastCommittedQty);
            syncInputMeta(lastCommittedQty);
            refreshState();
        }

        function requestSnapshot(version) {
            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: {
                    _: Date.now()
                },
                cache: false,
                dataType: 'html'
            }).done(function (html) {
                applySnapshot(html, version);
            });
        }

        function sendUpdate() {
            var version = changeVersion,
                qty = getQty();

            if (inFlight) {
                retrySync = true;
                return;
            }

            if (lastCommittedQty !== null && qty === lastCommittedQty) {
                return;
            }

            inFlight = true;
            retrySync = false;
            responseVersion = version;

            $.ajax({
                url: $form.attr('action'),
                type: ($form.attr('method') || 'post').toUpperCase(),
                data: $form.serialize(),
                cache: false,
                dataType: 'html'
            }).done(function () {
                if (version === changeVersion) {
                    requestSnapshot(version);
                }
            }).fail(function () {
                if (version === changeVersion) {
                    revertToCommittedQty();
                }
            }).always(function () {
                inFlight = false;

                if (retrySync || responseVersion !== changeVersion) {
                    retrySync = false;
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(sendUpdate, 0);
                }
            });
        }

        function scheduleUpdate() {
            changeVersion++;
            clearTimeout(saveTimer);
            saveTimer = setTimeout(sendUpdate, SAVE_DELAY);
        }

        function updateQty(nextQty) {
            var currentQty = getQty(),
                normalizedQty = clampQty(nextQty);

            if (normalizedQty === currentQty) {
                refreshState();
                return;
            }

            setQty(normalizedQty);
            refreshState();
            scheduleUpdate();
        }

        lastCommittedQty = getQty();
        setQty(lastCommittedQty);
        syncInputMeta(lastCommittedQty);
        refreshState();

        $row.on('click', '[data-role="qty-action"]', function (event) {
            var direction = $(this).data('direction'),
                currentQty = getQty(),
                step = getStep(),
                nextQty;

            event.preventDefault();

            if (direction === 'increase') {
                nextQty = currentQty + step;
            } else {
                nextQty = currentQty - step;
            }

            updateQty(nextQty);
        });

        $input.on('input', function () {
            refreshState();
        });

        $input.on('change', function () {
            var nextQty = clampQty(getQty());

            if (nextQty === getQty()) {
                refreshState();
                return;
            }

            setQty(nextQty);
            syncInputMeta(nextQty);
            refreshState();
            scheduleUpdate();
        });
    };
});
