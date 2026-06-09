<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Plugin\Checkout;

use Magento\Checkout\Controller\Cart\Index as CartIndex;
use Magento\Checkout\Controller\Index\Index as CheckoutIndex;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vendor\ZaloPay\Model\Order\PaymentStateApplier;

class RestoreQuoteOnReturnPluginTest extends TestCase
{
    private CheckoutSession $checkoutSession;
    private PaymentStateApplier $paymentStateApplier;
    private RedirectFactory $redirectFactory;
    private RestoreQuoteOnReturnPlugin $plugin;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->paymentStateApplier = $this->createMock(PaymentStateApplier::class);
        $this->redirectFactory = $this->createMock(RedirectFactory::class);
        $this->plugin = new RestoreQuoteOnReturnPlugin(
            $this->checkoutSession,
            $this->paymentStateApplier,
            $this->createMock(LoggerInterface::class),
            $this->redirectFactory
        );
    }

    public function testRedirectsCheckoutToPaymentAfterRestoringQuote(): void
    {
        $order = $this->createPendingZaloPayOrder();
        $redirect = $this->createMock(Redirect::class);

        $this->checkoutSession->method('getData')->willReturn(true);
        $this->checkoutSession->method('getLastRealOrder')->willReturn($order);
        $this->checkoutSession->expects(self::once())->method('restoreQuote')->willReturn(true);
        $this->paymentStateApplier->expects(self::once())
            ->method('markFailed')
            ->with('000000011', 'user_back');
        $this->redirectFactory->expects(self::once())->method('create')->willReturn($redirect);
        $redirect->expects(self::once())
            ->method('setPath')
            ->with('checkout', ['_fragment' => 'payment'])
            ->willReturnSelf();

        $result = $this->plugin->aroundExecute(
            $this->createMock(CheckoutIndex::class),
            static fn () => 'checkout-page'
        );

        self::assertSame($redirect, $result);
    }

    public function testRestoresQuoteWithoutRedirectingCart(): void
    {
        $order = $this->createPendingZaloPayOrder();

        $this->checkoutSession->method('getData')->willReturn(true);
        $this->checkoutSession->method('getLastRealOrder')->willReturn($order);
        $this->checkoutSession->expects(self::once())->method('restoreQuote')->willReturn(true);
        $this->redirectFactory->expects(self::never())->method('create');

        $result = $this->plugin->aroundExecute(
            $this->createMock(CartIndex::class),
            static fn () => 'cart-page'
        );

        self::assertSame('cart-page', $result);
    }

    public function testDoesNotInterceptCheckoutWithoutStartedGateway(): void
    {
        $this->checkoutSession->method('getData')->willReturn(false);
        $this->checkoutSession->expects(self::never())->method('restoreQuote');
        $this->paymentStateApplier->expects(self::never())->method('markFailed');
        $this->redirectFactory->expects(self::never())->method('create');

        $result = $this->plugin->aroundExecute(
            $this->createMock(CheckoutIndex::class),
            static fn () => 'checkout-page'
        );

        self::assertSame('checkout-page', $result);
    }

    private function createPendingZaloPayOrder(): Order
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('zalopay');

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(11);
        $order->method('getIncrementId')->willReturn('000000011');
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn(Order::STATE_NEW);

        return $order;
    }
}
