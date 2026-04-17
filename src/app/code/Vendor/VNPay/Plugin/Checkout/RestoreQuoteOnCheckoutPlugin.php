<?php
declare(strict_types=1);

namespace Vendor\VNPay\Plugin\Checkout;

use Magento\Checkout\Controller\Index\Index;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Vendor\VNPay\Model\Order\PaymentStateApplier;

class RestoreQuoteOnCheckoutPlugin
{
    private const REDIRECT_CACHE_KEY = 'peakgear_vnpay_redirect_cache';
    private const GATEWAY_STARTED_KEY = 'peakgear_vnpay_gateway_started';

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly PaymentStateApplier $paymentStateApplier,
        private readonly LoggerInterface $logger
    ) {
    }

    public function beforeExecute(Index $subject): void
    {
        if (!$this->shouldAttemptRestore()) {
            return;
        }

        $order = $this->checkoutSession->getLastRealOrder();

        try {
            $this->paymentStateApplier->markFailed((string)$order->getIncrementId(), 'user_back');
        } catch (\Throwable $exception) {
            $this->logger->warning('VNPay checkout return state update failed.', [
                'order_increment_id' => $order->getIncrementId(),
                'exception' => $exception,
            ]);
        }

        $this->checkoutSession->restoreQuote();
        $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
        $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
        $this->checkoutSession->setData('peakgear_successful_payment_order', null);
    }

    private function shouldAttemptRestore(): bool
    {
        $gatewayStarted = (bool)$this->checkoutSession->getData(self::GATEWAY_STARTED_KEY);

        if (!$gatewayStarted) {
            return false;
        }

        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order || !$order->getId()) {
            return false;
        }

        $method = strtolower((string)($order->getPayment() ? $order->getPayment()->getMethod() : ''));
        if (strpos($method, 'vnpay') === false) {
            return false;
        }

        return !in_array(
            (string)$order->getState(),
            [Order::STATE_PROCESSING, Order::STATE_COMPLETE],
            true
        );
    }
}
