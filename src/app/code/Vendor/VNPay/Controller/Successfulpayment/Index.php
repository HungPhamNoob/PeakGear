<?php
declare(strict_types=1);

namespace Vendor\VNPay\Controller\Successfulpayment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\View\Result\PageFactory;
use PeakGear\Cart\Controller\Select\Restore as RestoreDeselectedItems;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly RequestInterface $request,
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly RestoreDeselectedItems $restoreDeselectedItems
    ) {
    }

    public function execute()
    {
        $resultRedirect = $this->redirectFactory->create();
        $sessionSuccessOrderId = (string)$this->checkoutSession->getData('peakgear_successful_payment_order');
        $requestOrderId = trim((string)$this->request->getParam('order_id', ''));
        $lastOrder = $this->checkoutSession->getLastRealOrder();
        $hasSessionSuccessOrder = $sessionSuccessOrderId !== '';
        $effectiveOrderId = $hasSessionSuccessOrder
            ? $sessionSuccessOrderId
            : (($lastOrder && $lastOrder->getId()) ? (string)$lastOrder->getIncrementId() : '');

        if ($effectiveOrderId === '') {
            return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
        }

        if ($requestOrderId !== '' && $requestOrderId !== $effectiveOrderId) {
            return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
        }

        $resolvedOrder = $lastOrder && $lastOrder->getId() && (string)$lastOrder->getIncrementId() === $effectiveOrderId
            ? $lastOrder
            : $this->loadOrderByIncrementId($effectiveOrderId);

        if (!$resolvedOrder || !$resolvedOrder->getId()) {
            return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
        }

        $paymentMethod = (string)($resolvedOrder->getPayment() ? $resolvedOrder->getPayment()->getMethod() : '');
        $isOfflineMethod = $this->isOfflineMethod($paymentMethod);

        if ($hasSessionSuccessOrder) {
            if (!in_array(
                (string)$resolvedOrder->getState(),
                [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT, Order::STATE_PROCESSING, Order::STATE_COMPLETE],
                true
            )) {
                return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
            }
        } else {
            if (!$isOfflineMethod) {
                return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
            }

            if (!in_array(
                (string)$resolvedOrder->getState(),
                [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT, Order::STATE_PROCESSING, Order::STATE_COMPLETE],
                true
            )) {
                return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
            }

            // Persist fallback order id so reload keeps the successful-payment page for offline checkout.
            $this->checkoutSession->setData('peakgear_successful_payment_order', $effectiveOrderId);
        }

        $this->restoreDeselectedItems->restoreItems();

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Thanh toán thành công'));

        return $page;
    }

    private function loadOrderByIncrementId(string $incrementId): ?Order
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $items = $this->orderRepository->getList(
            $searchCriteriaBuilder->addFilter('increment_id', $incrementId)->create()
        )->getItems();
        $order = reset($items);

        return $order instanceof Order ? $order : null;
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
