define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/cookies'
], function ($, customerData) {
    'use strict';

    return function (config, element) {
        var root = element;
        var cart = customerData.get('cart');
        var toggle = root.querySelector('.minicart-toggle');
        var panel = root.querySelector('.peakgear-minicart-panel');
        var close = root.querySelector('.peakgear-minicart-close');
        var itemsNode = root.querySelector('.peakgear-minicart-items');
        var emptyNode = root.querySelector('.peakgear-minicart-empty');
        var counter = root.querySelector('.minicart-counter');
        var countNode = root.querySelector('.peakgear-minicart-count');
        var subtotalNode = root.querySelector('.peakgear-minicart-subtotal');
        var header = document.getElementById('peakgear-header');
        var isOpen = false;

        function setOpen(nextOpen) {
            if (!toggle || !panel) {
                return;
            }

            isOpen = nextOpen;
            panel.classList.toggle('open', nextOpen);
            panel.setAttribute('aria-hidden', nextOpen ? 'false' : 'true');
            toggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');

            if (header) {
                header.classList.toggle('minicart-open', nextOpen);
            }
        }

        function createTextElement(tagName, className, text) {
            var node = document.createElement(tagName);

            if (className) {
                node.className = className;
            }
            node.textContent = text || '';

            return node;
        }

        function decodeHtml(value) {
            var textarea = document.createElement('textarea');
            textarea.innerHTML = value || '';

            return textarea.value;
        }

        function renderCartItem(item) {
            var row = document.createElement('div');
            var itemId = item.item_id || '';
            var productNameHtml = item.product_name || 'Sản phẩm';
            var productName = decodeHtml(productNameHtml);
            var image = item.product_image || {};
            var media = document.createElement(item.product_url ? 'a' : 'span');
            var body = document.createElement('div');
            var title = document.createElement(item.product_url ? 'a' : 'span');
            var meta = document.createElement('div');
            var price = document.createElement('strong');
            var remove = document.createElement('button');

            row.className = 'peakgear-minicart-item';
            media.className = 'peakgear-minicart-thumb';
            body.className = 'peakgear-minicart-item-body';
            title.className = 'peakgear-minicart-item-name';
            meta.className = 'peakgear-minicart-item-meta';

            if (item.product_url) {
                media.href = item.product_url;
                title.href = item.product_url;
            }

            if (image.src) {
                var img = document.createElement('img');
                img.src = image.src;
                img.alt = decodeHtml(image.alt || productName);
                img.loading = 'lazy';
                media.appendChild(img);
            } else {
                media.appendChild(createTextElement('span', '', 'PeakGear'));
            }

            title.innerHTML = productNameHtml;
            meta.appendChild(createTextElement('span', '', 'SL: ' + (item.qty || 0)));
            price.innerHTML = item.product_price || '';
            meta.appendChild(price);

            remove.type = 'button';
            remove.className = 'peakgear-minicart-remove';
            remove.setAttribute('aria-label', 'Xóa ' + productName);
            remove.setAttribute('data-cart-item', itemId);
            remove.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>';

            body.appendChild(title);
            body.appendChild(meta);
            row.appendChild(media);
            row.appendChild(body);
            row.appendChild(remove);

            return row;
        }

        function renderMiniCart(data) {
            var count = data && Number(data.summary_count) ? Number(data.summary_count) : 0;
            var items = data && Array.isArray(data.items) ? data.items : [];

            if (counter) {
                counter.textContent = count;
                counter.style.display = count > 0 ? '' : 'none';
            }

            if (countNode) {
                countNode.textContent = count + ' sản phẩm';
            }

            if (subtotalNode) {
                subtotalNode.innerHTML = data && data.subtotal ? data.subtotal : '0đ';
            }

            if (!itemsNode || !emptyNode) {
                return;
            }

            itemsNode.innerHTML = '';
            if (!items.length) {
                emptyNode.hidden = false;
                itemsNode.hidden = true;
                return;
            }

            emptyNode.hidden = true;
            itemsNode.hidden = false;
            items.forEach(function (item) {
                itemsNode.appendChild(renderCartItem(item));
            });
        }

        function removeCartItem(button) {
            var itemId = button && button.getAttribute('data-cart-item');

            if (!itemId || !config.removeItemUrl) {
                return;
            }

            $.ajax({
                url: config.removeItemUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    item_id: itemId,
                    form_key: $.mage.cookies.get('form_key')
                },
                beforeSend: function () {
                    button.disabled = true;
                    button.classList.add('is-loading');
                }
            }).always(function () {
                button.disabled = false;
                button.classList.remove('is-loading');
                customerData.invalidate(['cart']);
                customerData.reload(['cart'], true);
            });
        }

        if (toggle && panel) {
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                setOpen(!isOpen);
            });

            panel.addEventListener('click', function (event) {
                var removeButton = event.target.closest('.peakgear-minicart-remove');
                event.stopPropagation();

                if (removeButton) {
                    removeCartItem(removeButton);
                }
            });

            if (close) {
                close.addEventListener('click', function (event) {
                    event.preventDefault();
                    setOpen(false);
                });
            }

            document.addEventListener('click', function (event) {
                if (isOpen && !panel.contains(event.target) && !toggle.contains(event.target)) {
                    setOpen(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && isOpen) {
                    setOpen(false);
                }
            });
        }

        cart.subscribe(function (data) {
            renderMiniCart(data || {});
        });
        renderMiniCart(cart() || {});
    };
});
