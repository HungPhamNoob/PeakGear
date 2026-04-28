/**
 * PeakGear Cart Select Widget
 *
 * Manages per-item selection checkboxes on the cart page.
 * - Renders "Select all" header checkbox
 * - Tracks checked state per item ID (persisted to sessionStorage)
 * - Recalculates sidebar subtotal & grand total in real-time
 *
 * NOTE: Checkout button interception is handled separately in select-init.phtml
 * using a DOM capture-phase listener (which fires before any jQuery/KO handler).
 */
define([
    'jquery'
], function ($) {
    'use strict';

    var SESSION_KEY = 'peakgear_cart_deselected';

    /* ─── Helpers ──────────────────────────────────────────────────── */

    /** Read the set of deselected item IDs from sessionStorage. @return {Set<string>} */
    function loadDeselected() {
        try {
            var raw = sessionStorage.getItem(SESSION_KEY);
            return raw ? new Set(JSON.parse(raw)) : new Set();
        } catch (e) {
            return new Set();
        }
    }

    /** Persist the set of deselected item IDs to sessionStorage. */
    function saveDeselected(set) {
        try {
            sessionStorage.setItem(SESSION_KEY, JSON.stringify(Array.from(set)));
        } catch (e) { /* quota / private mode – silent */ }
    }

    /** Format a number as Vietnamese đồng string (e.g. "₫177,000.00"). */
    function formatVND(amount) {
        return '\u20ab' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /* ─── Main widget ───────────────────────────────────────────────── */

    return function initCartSelect() {

        /* Only run on the cart page */
        if (!$('#shopping-cart-table').length) {
            return;
        }

        var deselected = loadDeselected();

        /* ── Collect all item rows ─────────────────────────────────── */
        var items = [];

        $('tbody[data-select-item-id]').each(function () {
            var $row = $(this);
            var id   = String($row.data('selectItemId') || '');
            if (!id) { return; }

            var $cb = $row.find('[data-role="cart-item-select"]').first();
            if (!$cb.length) { return; }

            items.push({
                $row:      $row,
                $cb:       $cb,
                id:        id,
                unitPrice: parseFloat($row.data('unitPrice')) || 0
            });
        });

        if (!items.length) { return; }

        /* ── Inject "Select all" header checkbox ────────────────────── */
        var $selectAllWrapper = $(
            '<div class="cart-select-all-wrapper">' +
                '<label class="cart-item-select-label cart-select-all-label" ' +
                       'title="Ch\u1ecdn / b\u1ecf ch\u1ecdn t\u1ea5t c\u1ea3">' +
                    '<input type="checkbox" id="cart-select-all" ' +
                           'class="cart-item-select" checked ' +
                           'aria-label="Ch\u1ecdn t\u1ea5t c\u1ea3 s\u1ea3n ph\u1ea9m">' +
                    '<span class="cart-item-select-indicator" aria-hidden="true"></span>' +
                    '<span class="cart-select-all-text">T\u1ea5t c\u1ea3</span>' +
                '</label>' +
            '</div>'
        );
        $('#shopping-cart-table').before($selectAllWrapper);
        var $selectAll = $selectAllWrapper.find('#cart-select-all');

        /* ── Restore persisted deselected state ─────────────────────── */
        items.forEach(function (entry) {
            if (deselected.has(entry.id)) {
                entry.$cb.prop('checked', false);
            }
        });

        /* ── Sidebar total helpers ──────────────────────────────────── */

        function readShippingAmount() {
            var $shippingRow = $('#cart-totals .totals.shipping.excl');
            if (!$shippingRow.length) {
                $shippingRow = $('#cart-totals .totals-tax-shipping');
            }
            var $price = $shippingRow.find('.price').first();
            if (!$price.length) { return 0; }
            var raw = $price.text().replace(/[^\d.]/g, '');
            return parseFloat(raw) || 0;
        }

        var shippingAmount = readShippingAmount();

        function recalcTotals() {
            var subtotal = 0;

            items.forEach(function (entry) {
                if (entry.$cb.prop('checked')) {
                    var $qtyInput = entry.$row.find('[data-role="cart-item-qty"]').first();
                    var qty = parseFloat($qtyInput.val()) || 1;
                    subtotal += entry.unitPrice * qty;
                }
            });

            var grandTotal = subtotal + shippingAmount;

            var $subtotalRow = $('#cart-totals .totals.sub');
            if ($subtotalRow.length) {
                $subtotalRow.find('td.amount .price').text(formatVND(subtotal));
            }

            var $grandRow = $('#cart-totals tr.grand.totals');
            if ($grandRow.length) {
                $grandRow.find('td.amount .price').text(formatVND(grandTotal));
            }

            var checkedCount = items.filter(function (e) {
                return e.$cb.prop('checked');
            }).length;

            $selectAll.prop('indeterminate', checkedCount > 0 && checkedCount < items.length);
            $selectAll.prop('checked', checkedCount === items.length);

            updateSelectedCount(checkedCount);
        }

        function updateSelectedCount(count) {
            var $badge = $('#cart-selected-count');
            if (!$badge.length) {
                $badge = $('<p id="cart-selected-count" class="cart-selected-count"></p>');
                $('#cart-totals').before($badge);
            }
            if (count === items.length) {
                $badge.hide();
            } else {
                $badge.text('Đã chọn ' + count + '/' + items.length + ' sản phẩm').show();
            }
        }

        /* ── Per-item checkbox change ────────────────────────────────── */
        items.forEach(function (entry) {
            entry.$cb.on('change', function () {
                if ($(this).prop('checked')) {
                    deselected.delete(entry.id);
                } else {
                    deselected.add(entry.id);
                }
                saveDeselected(deselected);
                recalcTotals();
            });
        });

        /* ── "Select all" checkbox ───────────────────────────────────── */
        $selectAll.on('change', function () {
            var checked = $(this).prop('checked');
            items.forEach(function (entry) {
                entry.$cb.prop('checked', checked);
                if (checked) {
                    deselected.delete(entry.id);
                } else {
                    deselected.add(entry.id);
                }
            });
            saveDeselected(deselected);
            recalcTotals();
        });

        /* ── Re-calc when qty input changes ─────────────────────────── */
        $('#shopping-cart-table').on('change', '[data-role="cart-item-qty"]', function () {
            setTimeout(recalcTotals, 50);
        });

        /* Re-calc after cart-qty.js triggers an AJAX snapshot reload */
        $(document).on('ajaxComplete', function () {
            setTimeout(recalcTotals, 400);
        });

        /* ── Initial render ─────────────────────────────────────────── */
        recalcTotals();
    };
});
