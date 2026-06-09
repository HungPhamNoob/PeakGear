<?php
declare(strict_types=1);

namespace PeakGear\Account\Plugin\OrderCancellation;

use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\OrderCancellation\Model\CancelOrder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PeakGear\Account\Setup\Patch\Data\AddCancellationRequestedStatus;
use PHPUnit\Framework\TestCase;

class CreateCancellationRequestPluginTest extends TestCase
{
    public function testCreatesRequestWithoutCallingMagentoCancellation(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $escaper = $this->createMock(Escaper::class);
        $plugin = new CreateCancellationRequestPlugin($repository, $escaper);
        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);
        $proceedCalled = false;

        $order->method('getStatus')->willReturn('processing');
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $order->method('getPayment')->willReturn($payment);
        $payment->method('getAmountPaid')->willReturn(100000.0);
        $escaper->expects(self::once())->method('escapeHtml')->with('Đổi ý')->willReturn('Đổi ý');
        $order->expects(self::once())
            ->method('setStatus')
            ->with(AddCancellationRequestedStatus::STATUS)
            ->willReturnSelf();
        $order->expects(self::exactly(2))
            ->method('addCommentToStatusHistory')
            ->willReturnSelf();
        $repository->expects(self::once())->method('save')->with($order)->willReturn($order);

        $result = $plugin->aroundExecute(
            $this->createMock(CancelOrder::class),
            static function () use (&$proceedCalled): void {
                $proceedCalled = true;
            },
            $order,
            'Đổi ý'
        );

        self::assertSame($order, $result);
        self::assertFalse($proceedCalled);
    }

    public function testRejectsDuplicateRequest(): void
    {
        $plugin = new CreateCancellationRequestPlugin(
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(Escaper::class)
        );
        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn(AddCancellationRequestedStatus::STATUS);

        $this->expectException(LocalizedException::class);

        $plugin->aroundExecute(
            $this->createMock(CancelOrder::class),
            static fn () => $order,
            $order,
            'Đổi ý'
        );
    }
}
