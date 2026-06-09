<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Plugin\Checkout;

use Magento\Checkout\Controller\Index\Index as CheckoutIndex;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Vendor\ZaloPay\Model\Order\PaymentStateApplier;

class RestoreQuoteOnReturnPlugin
{
    private const REDIRECT_CACHE_KEY = 'peakgear_zalopay_redirect_cache';
    private const GATEWAY_STARTED_KEY = 'peakgear_zalopay_gateway_started';

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly PaymentStateApplier $paymentStateApplier,
        private readonly LoggerInterface $logger,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    public function aroundExecute($subject, callable $proceed)
    {
        if (!$this->shouldAttemptRestore()) {
            return $proceed();
        }

        $order = $this->checkoutSession->getLastRealOrder();

        try {
            $this->paymentStateApplier->markFailed((string)$order->getIncrementId(), 'user_back');
        } catch (\Throwable $exception) {
            $this->logger->warning('ZaloPay checkout return state update failed.', [
                'order_increment_id' => $order->getIncrementId(),
                'exception' => $exception,
            ]);
        }

        $quoteRestored = $this->checkoutSession->restoreQuote();
        $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
        $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
        $this->checkoutSession->setData('peakgear_successful_payment_order', null);

        if ($quoteRestored && $subject instanceof CheckoutIndex) {
            return $this->redirectFactory->create()->setPath('checkout', ['_fragment' => 'payment']);
        }

        return $proceed();
    }

    private function shouldAttemptRestore(): bool
    {
        if (!(bool)$this->checkoutSession->getData(self::GATEWAY_STARTED_KEY)) {
            return false;
        }

        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order || !$order->getId()) {
            return false;
        }

        $payment = $order->getPayment();
        $method = strtolower((string)($payment ? $payment->getMethod() : ''));
        if (strpos($method, 'zalopay') === false) {
            return false;
        }

        return !in_array(
            (string)$order->getState(),
            [Order::STATE_PROCESSING, Order::STATE_COMPLETE],
            true
        );
    }
}
