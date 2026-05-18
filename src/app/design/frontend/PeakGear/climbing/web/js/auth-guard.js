define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function ($, customerData, $t) {
    'use strict';

    var initialized = false;
    var authConfig = {
        loginUrl: '/customer/account/login',
        messages: {}
    };

    function isLoggedIn() {
        var customer = customerData.get('customer')();

        return !!(customer && (customer.firstname || customer.fullname || customer.email));
    }

    function getMessage(action, fallback) {
        return fallback ||
            authConfig.messages[action] ||
            authConfig.messages['default'] ||
            $t('Bạn cần đăng nhập để tiếp tục.');
    }

    function showTopMessage(message, type) {
        var $placeholder = $('[data-placeholder="messages"]').first(),
            messageClass = type === 'error' ? 'message-error error' : 'message-success success',
            $messages,
            $message;

        if (!$placeholder.length) {
            return;
        }

        $messages = $placeholder.children('.messages');
        if (!$messages.length) {
            $messages = $('<div class="messages"></div>');
            $placeholder.append($messages);
        }

        $message = $('<div/>', {
            'class': 'message ' + messageClass
        }).append($('<div/>').text(message));

        $messages.append($message);

        setTimeout(function () {
            $message.addClass('toast-dismiss');
            setTimeout(function () {
                $message.remove();
            }, 350);
        }, 3500);
    }

    function buildModal() {
        var $modal = $('#peakgear-auth-guard-modal');

        if ($modal.length) {
            return $modal;
        }

        $modal = $(
            '<div id="peakgear-auth-guard-modal" class="pg-auth-guard-modal" aria-hidden="true">' +
                '<div class="pg-auth-guard-modal__backdrop" data-role="close"></div>' +
                '<div class="pg-auth-guard-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pg-auth-guard-title">' +
                    '<button type="button" class="pg-auth-guard-modal__close" data-role="close" aria-label="' + $t('Đóng') + '">' +
                        '<span>&times;</span>' +
                    '</button>' +
                    '<div class="pg-auth-guard-modal__icon" aria-hidden="true">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">' +
                            '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>' +
                            '<polyline points="10 17 15 12 10 7"/>' +
                            '<line x1="15" y1="12" x2="3" y2="12"/>' +
                        '</svg>' +
                    '</div>' +
                    '<h3 id="pg-auth-guard-title" class="pg-auth-guard-modal__title"></h3>' +
                    '<p class="pg-auth-guard-modal__text"></p>' +
                    '<div class="pg-auth-guard-modal__actions">' +
                        '<a class="pg-auth-guard-modal__primary" data-role="login" href="#">' + $t('Đăng nhập ngay') + '</a>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        $modal.on('click', '[data-role="close"]', function () {
            closeModal();
        });

        $(document).on('keydown.peakgearAuthGuard', function (event) {
            if (event.key === 'Escape' && $modal.attr('aria-hidden') === 'false') {
                closeModal();
            }
        });

        $('body').append($modal);

        return $modal;
    }

    function closeModal() {
        var $modal = $('#peakgear-auth-guard-modal');

        if (!$modal.length) {
            return;
        }

        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('pg-auth-modal-open');
    }

    function openModal(message, options) {
        var $modal = buildModal(),
            loginUrl = options.loginUrl || authConfig.loginUrl,
            title = options.title || $t('Đăng nhập để tiếp tục');

        $modal.find('.pg-auth-guard-modal__title').text(title);
        $modal.find('.pg-auth-guard-modal__text').text(message);
        $modal.find('[data-role="login"]').attr('href', loginUrl);
        $modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('pg-auth-modal-open');
    }

    function resolveProtectedElement(target) {
        if (!target || !target.closest) {
            return null;
        }

        return target.closest('[data-auth-required="1"]');
    }

    function isSubmitTriggerWithinForm(target) {
        if (!target || !target.closest) {
            return false;
        }

        return !!target.closest(
            'button[type="submit"], button:not([type]), input[type="submit"], .action.tocart'
        );
    }

    function handleBlockedAction(element) {
        var $element = $(element),
            action = ($element.data('auth-action') || '').toString(),
            message = getMessage(action, $element.data('auth-message')),
            title = $element.data('auth-title') || $t('Đăng nhập để tiếp tục');

        openModal(message, {
            title: title,
            loginUrl: $element.data('login-url')
        });
    }

    function shouldIgnoreEvent(event) {
        return event.defaultPrevented || isLoggedIn();
    }

    function initCaptureHandlers() {
        document.addEventListener('click', function (event) {
            var protectedElement;

            if (shouldIgnoreEvent(event)) {
                return;
            }

            protectedElement = resolveProtectedElement(event.target);
            if (!protectedElement) {
                return;
            }

            if (
                protectedElement.tagName &&
                protectedElement.tagName.toLowerCase() === 'form' &&
                !isSubmitTriggerWithinForm(event.target)
            ) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            handleBlockedAction(protectedElement);
        }, true);

        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (shouldIgnoreEvent(event) || !form || !form.matches || !form.matches('form[data-auth-required="1"]')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            handleBlockedAction(form);
        }, true);
    }

    return function (config) {
        authConfig = $.extend(true, {}, authConfig, config || {});

        if (initialized) {
            return;
        }

        initialized = true;
        initCaptureHandlers();
    };
});
