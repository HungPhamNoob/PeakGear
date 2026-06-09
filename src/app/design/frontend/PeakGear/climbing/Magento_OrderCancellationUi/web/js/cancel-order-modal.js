define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'Magento_Customer/js/customer-data'
], function ($, modal, customerData) {
    'use strict';

    return function (config, element) {
        var orderId = config.order_id;
        var modalElement = $('#cancel-order-modal-' + orderId);
        var options = {
            type: 'popup',
            responsive: true,
            innerScroll: true,
            modalClass: 'pg-cancellation-modal',
            title: $.mage.__('Yêu cầu hủy đơn'),
            buttons: [{
                text: $.mage.__('Để sau'),
                class: 'action-secondary action-dismiss close-modal-button',
                click: function () {
                    this.closeModal();
                }
            }, {
                text: $.mage.__('Gửi yêu cầu hủy'),
                class: 'action-primary action-accept cancel-order-button',
                click: function () {
                    var thisModal = this;
                    var reason = $('#cancel-order-reason-' + orderId).find(':selected').text();
                    var mutation = [
                        'mutation cancelOrder($order_id: ID!, $reason: String!) {',
                        '  cancelOrder(input: {order_id: $order_id, reason: $reason}) {',
                        '    error',
                        '    order { status }',
                        '  }',
                        '}'
                    ].join('\n');

                    $.ajax({
                        showLoader: true,
                        type: 'POST',
                        url: config.url + 'graphql',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            query: mutation,
                            variables: {
                                order_id: orderId,
                                reason: reason
                            }
                        })
                    }).done(function (response) {
                        var result = response && response.data && response.data.cancelOrder;
                        var type = result && !result.error ? 'success' : 'error';
                        var message = type === 'success'
                            ? 'Yêu cầu hủy đã được gửi. Vui lòng chờ quản trị viên duyệt'
                                + ' và xử lý hoàn tiền nếu đơn đã thanh toán.'
                            : (result && result.error) || 'Không thể gửi yêu cầu hủy lúc này. Vui lòng thử lại.';

                        customerData.set('messages', {
                            messages: [{
                                text: $.mage.__(message),
                                type: type
                            }]
                        });

                        if (type === 'success') {
                            window.location.reload();
                        }
                    }).fail(function () {
                        customerData.set('messages', {
                            messages: [{
                                text: $.mage.__('Không thể gửi yêu cầu hủy lúc này. Vui lòng thử lại.'),
                                type: 'error'
                            }]
                        });
                    }).always(function () {
                        thisModal.closeModal(true);
                    });
                }
            }]
        };

        modal(options, modalElement);
        modalElement.closest('.modal-popup').addClass('pg-cancellation-modal');

        $(element).on('click', function (event) {
            event.preventDefault();
            modalElement.modal('openModal');
        });
    };
});
