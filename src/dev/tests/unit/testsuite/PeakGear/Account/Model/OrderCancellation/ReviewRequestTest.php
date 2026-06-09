<?php
declare(strict_types=1);

namespace PeakGear\Account\Model\OrderCancellation;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\Payment;
use PeakGear\Account\Setup\Patch\Data\AddCancellationApprovedStatus;
use PeakGear\Account\Setup\Patch\Data\AddCancellationRequestedStatus;
use PHPUnit\Framework\TestCase;

class ReviewRequestTest extends TestCase
{
    public function testPaidApprovalWaitsForRefund(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);
        $service = new ReviewRequest($repository, $this->createMock(OrderConfig::class));

        $repository->method('get')->with(10)->willReturn($order);
        $order->method('getStatus')->willReturn(AddCancellationRequestedStatus::STATUS);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $payment->method('getAmountPaid')->willReturn(100000.0);
        $order->expects(self::never())->method('cancel');
        $order->expects(self::once())
            ->method('setStatus')
            ->with(AddCancellationApprovedStatus::STATUS)
            ->willReturnSelf();
        $order->expects(self::once())->method('addCommentToStatusHistory')->willReturnSelf();
        $repository->expects(self::once())->method('save')->with($order)->willReturn($order);

        self::assertSame($order, $service->approve(10));
    }

    public function testUnpaidApprovalCancelsOrder(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);
        $service = new ReviewRequest($repository, $this->createMock(OrderConfig::class));

        $repository->method('get')->with(11)->willReturn($order);
        $order->method('getStatus')->willReturn(AddCancellationRequestedStatus::STATUS);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $order->method('canCancel')->willReturn(true);
        $payment->method('getAmountPaid')->willReturn(null);
        $order->expects(self::once())->method('cancel')->willReturnSelf();
        $order->expects(self::once())->method('addCommentToStatusHistory')->willReturnSelf();
        $repository->expects(self::once())->method('save')->with($order)->willReturn($order);

        self::assertSame($order, $service->approve(11));
    }
}
