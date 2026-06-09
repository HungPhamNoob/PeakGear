define([], function () {
    'use strict';

    return function (config, element) {
        var root = element;
        var toggle = root.querySelector('[data-role="pg-contact-widget-toggle"]');
        var actions = root.querySelector('[data-role="pg-contact-widget-actions"]');

        if (!toggle || !actions) {
            return;
        }

        function setOpen(isOpen) {
            root.classList.toggle('is-open', isOpen);
            root.classList.toggle('is-collapsed', !isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            toggle.setAttribute('aria-label', isOpen ? 'Đóng công cụ liên hệ' : 'Mở công cụ liên hệ');
            actions.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        }

        setOpen(root.getAttribute('data-default-open') === '1');

        toggle.addEventListener('click', function () {
            setOpen(!root.classList.contains('is-open'));
        });

        document.addEventListener('click', function (event) {
            if (!root.classList.contains('is-open')) {
                return;
            }

            if (!root.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && root.classList.contains('is-open')) {
                setOpen(false);
            }
        });
    };
});
