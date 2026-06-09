<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Model\Order;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class PaymentStateApplierTest extends TestCase
{
    public function testFailedPaymentRemainsPendingInsteadOfCanceled(): void
    {
        [$service, $repository, $order, $payment] = $this->createService();

        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getPayment')->willReturn($payment);
        $payment->method('getAdditionalInformation')->willReturn(null);
        $payment->expects(self::once())
            ->method('setAdditionalInformation')
            ->with('peakgear_zalopay_last_incomplete_reason', 'user_back')
            ->willReturnSelf();
        $order->expects(self::once())->method('setState')->with(Order::STATE_PENDING_PAYMENT)->willReturnSelf();
        $order->expects(self::once())->method('setStatus')->with(Order::STATE_PENDING_PAYMENT)->willReturnSelf();
        $order->expects(self::once())->method('addCommentToStatusHistory')->willReturnSelf();
        $repository->expects(self::once())->method('save')->with($order);

        $service->markFailed('000000001', 'user_back');
    }

    private function createService(): array
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $factory = $this->createMock(SearchCriteriaBuilderFactory::class);
        $builder = $this->createMock(SearchCriteriaBuilder::class);
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $result = $this->createMock(OrderSearchResultInterface::class);
        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);

        $factory->method('create')->willReturn($builder);
        $builder->method('addFilter')->willReturnSelf();
        $builder->method('create')->willReturn($criteria);
        $repository->method('getList')->with($criteria)->willReturn($result);
        $result->method('getItems')->willReturn([$order]);

        return [new PaymentStateApplier($repository, $factory), $repository, $order, $payment];
    }
}
