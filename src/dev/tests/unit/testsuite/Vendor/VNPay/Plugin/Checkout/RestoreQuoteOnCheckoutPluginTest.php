<?php
declare(strict_types=1);

namespace Vendor\VNPay\Plugin\Checkout;

use Magento\Checkout\Controller\Index\Index;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vendor\VNPay\Model\Order\PaymentStateApplier;

class RestoreQuoteOnCheckoutPluginTest extends TestCase
{
    public function testRestoresQuoteWithoutCancelingOrderWhenCustomerReturns(): void
    {
        $session = $this->createMock(CheckoutSession::class);
        $stateApplier = $this->createMock(PaymentStateApplier::class);
        $plugin = new RestoreQuoteOnCheckoutPlugin(
            $session,
            $stateApplier,
            $this->createMock(LoggerInterface::class)
        );
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('vnpay');
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(12);
        $order->method('getIncrementId')->willReturn('000000012');
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn(Order::STATE_NEW);

        $session->method('getData')->willReturn(true);
        $session->method('getLastRealOrder')->willReturn($order);
        $stateApplier->expects(self::once())->method('markFailed')->with('000000012', 'user_back');
        $session->expects(self::once())->method('restoreQuote')->willReturn(true);

        $plugin->beforeExecute($this->createMock(Index::class));
    }
}
