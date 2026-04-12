<?php
declare(strict_types=1);

namespace Vendor\VNPay\Controller\Successfulpayment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Model\Order;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly RequestInterface $request,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function execute()
    {
        $resultRedirect = $this->redirectFactory->create();
        $sessionSuccessOrderId = (string)$this->checkoutSession->getData('peakgear_successful_payment_order');
        $requestOrderId = trim((string)$this->request->getParam('order_id', ''));
        $lastOrder = $this->checkoutSession->getLastRealOrder();
        $hasSessionSuccessOrder = $sessionSuccessOrderId !== '';
        $lastOrderIncrementId = ($lastOrder && $lastOrder->getId()) ? (string)$lastOrder->getIncrementId() : '';
        $paymentMethod = (string)($lastOrder && $lastOrder->getPayment() ? $lastOrder->getPayment()->getMethod() : '');
        $isOfflineMethod = $this->isOfflineMethod($paymentMethod);
        $effectiveOrderId = $hasSessionSuccessOrder ? $sessionSuccessOrderId : $lastOrderIncrementId;

        if (!$lastOrder || !$lastOrder->getId() || $effectiveOrderId === '') {
            return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
        }

        if ($requestOrderId !== '' && $requestOrderId !== $effectiveOrderId) {
            return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
        }

        if ($lastOrderIncrementId !== $effectiveOrderId) {
            return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
        }

        if ($hasSessionSuccessOrder) {
            if (!in_array($lastOrder->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
                return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
            }
        } else {
            if (!$isOfflineMethod) {
                return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
            }

            if (!in_array(
                $lastOrder->getState(),
                [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT, Order::STATE_PROCESSING, Order::STATE_COMPLETE],
                true
            )) {
                return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
            }

            // Persist fallback order id so reload keeps the successful-payment page for offline checkout.
            $this->checkoutSession->setData('peakgear_successful_payment_order', $effectiveOrderId);
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Thanh toán thành công'));

        return $page;
    }

    private function isOfflineMethod(string $method): bool
    {
        $method = strtolower($method);

        return $method === 'checkmo'
            || $method === 'cashondelivery'
            || str_contains($method, 'store')
            || str_contains($method, 'cash')
            || str_contains($method, 'cod');
    }
}
