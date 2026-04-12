define([], function () {
    'use strict';

    return function (config, element) {
        if (!element) {
            return;
        }

        var toggleBtn = element;
        if (!toggleBtn) {
            return;
        }

        var isModalOpen = false;
        var modalElement;

        function getMainContentHost() {
            return document.getElementById('maincontent');
        }

        function ensureModal() {
            if (modalElement) {
                return;
            }

            modalElement = document.createElement('div');
            modalElement.className = 'modal-popup currency-converter-modal';
            modalElement.id = 'currencyConverterModal';
            modalElement.style.display = 'none';
            modalElement.innerHTML = '' +
                '<div class="modal-inner-wrap" role="dialog" aria-modal="true" aria-labelledby="currencyConverterTitle">' +
                '  <header class="modal-header">' +
                '    <h1 class="modal-title" id="currencyConverterTitle">Shipping Address</h1>' +
                '    <button type="button" class="action-close" data-role="closeBtn" aria-label="Close">Close</button>' +
                '  </header>' +
                '  <div class="modal-content">' +
                '    <form class="form form-shipping-address" id="co-shipping-form" data-hasrequired="* Required Fields">' +
                '      <div id="shipping-new-address-form" class="fieldset address">' +
                '        <div class="field"><label class="label" for="currency-first-name"><span>First Name</span></label><div class="control"><input type="text" id="currency-first-name" class="input-text" value="Phạm" /></div></div>' +
                '        <div class="field"><label class="label" for="currency-last-name"><span>Last Name</span></label><div class="control"><input type="text" id="currency-last-name" class="input-text" value="Việt Hưng" /></div></div>' +
                '        <div class="field"><label class="label" for="currency-company"><span>Company</span></label><div class="control"><input type="text" id="currency-company" class="input-text" value="" /></div></div>' +
                '        <div class="field street"><label class="label" for="currency-street-1"><span>Street Address</span></label><div class="control"><input type="text" id="currency-street-1" class="input-text" value="Street Address: Line 1" /></div></div>' +
                '        <div class="field"><label class="label" for="currency-country"><span>Country</span></label><div class="control"><select id="currency-country" class="select"><option selected>United States</option></select></div></div>' +
                '        <div class="field"><label class="label" for="currency-region"><span>State/Province</span></label><div class="control"><select id="currency-region" class="select"><option selected>Please select a region, state or province.</option></select></div></div>' +
                '        <div class="field"><label class="label" for="currency-city"><span>City</span></label><div class="control"><input type="text" id="currency-city" class="input-text" value="" /></div></div>' +
                '        <div class="field"><label class="label" for="currency-zip"><span>Zip/Postal Code</span></label><div class="control"><input type="text" id="currency-zip" class="input-text" value="" /></div></div>' +
                '        <div class="field"><label class="label" for="currency-phone"><span>Phone Number</span></label><div class="control"><input type="tel" id="currency-phone" class="input-text" value="" /></div></div>' +
                '        <div class="field"><span class="label"><span>Tooltip</span></span><div class="control"><span class="field-tooltip toggle"><a href="#" class="field-tooltip-action" aria-label="Tooltip"></a><span class="field-tooltip-content">Tooltip</span></span></div></div>' +
                '        <div class="field choice"><input type="checkbox" class="checkbox" id="shipping-save-in-address-book" /><label class="label" for="shipping-save-in-address-book"><span>Save in address book</span></label></div>' +
                '      </div>' +
                '    </form>' +
                '  </div>' +
                '</div>';

            var host = getMainContentHost();
            if (!host) {
                modalElement = null;
                return;
            }

            host.appendChild(modalElement);

            modalElement.querySelector('[data-role="closeBtn"]').addEventListener('click', function (event) {
                event.preventDefault();
                closeModal();
            });

            modalElement.addEventListener('click', function (event) {
                if (event.target === modalElement) {
                    closeModal();
                }
            });
        }

        function openModal() {
            ensureModal();
            if (!modalElement) {
                return;
            }
            modalElement.style.display = 'block';
            isModalOpen = true;
        }

        function closeModal() {
            if (modalElement) {
                modalElement.style.display = 'none';
            }
            isModalOpen = false;
        }

        toggleBtn.addEventListener('click', function (event) {
            event.preventDefault();
            if (isModalOpen) {
                closeModal();
                return;
            }

            openModal();
        });

        ensureModal();
    };
});