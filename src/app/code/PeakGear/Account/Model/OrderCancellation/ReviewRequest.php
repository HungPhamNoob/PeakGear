<?php
declare(strict_types=1);

namespace PeakGear\Account\Model\OrderCancellation;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use PeakGear\Account\Setup\Patch\Data\AddCancellationApprovedStatus;
use PeakGear\Account\Setup\Patch\Data\AddCancellationRequestedStatus;

class ReviewRequest
{
    private const PREVIOUS_STATUS_KEY = 'peakgear_status_before_cancellation_request';

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderConfig $orderConfig
    ) {
    }

    public function approve(int $orderId): Order
    {
        $order = $this->getRequestedOrder($orderId);
        $payment = $order->getPayment();
        $hasPaidAmount = ($payment && (float)$payment->getAmountPaid() > 0)
            || $order->getState() === Order::STATE_PROCESSING;

        if (!$hasPaidAmount && $order->canCancel()) {
            $order->cancel();
            $order->addCommentToStatusHistory(
                'Quản trị viên đã duyệt yêu cầu hủy. Đơn chưa thu tiền và đã được hủy.',
                false,
                true
            );
        } else {
            $order->setStatus(AddCancellationApprovedStatus::STATUS);
            $order->addCommentToStatusHistory(
                'Quản trị viên đã duyệt yêu cầu hủy. Vui lòng tạo Credit Memo/hoàn tiền cho khách.',
                AddCancellationApprovedStatus::STATUS,
                true
            );
        }

        return $this->orderRepository->save($order);
    }

    public function reject(int $orderId): Order
    {
        $order = $this->getRequestedOrder($orderId);
        $payment = $order->getPayment();
        $previousStatus = $payment
            ? (string)$payment->getAdditionalInformation(self::PREVIOUS_STATUS_KEY)
            : '';

        if ($previousStatus === '') {
            $previousStatus = (string)$this->orderConfig->getStateDefaultStatus($order->getState());
        }

        $order->setStatus($previousStatus);
        $order->addCommentToStatusHistory(
            'Quản trị viên đã từ chối yêu cầu hủy.',
            $previousStatus,
            true
        );

        return $this->orderRepository->save($order);
    }

    private function getRequestedOrder(int $orderId): Order
    {
        $order = $this->orderRepository->get($orderId);
        if (!$order instanceof Order || $order->getStatus() !== AddCancellationRequestedStatus::STATUS) {
            throw new LocalizedException(__('Đơn hàng không có yêu cầu hủy đang chờ duyệt.'));
        }

        return $order;
    }
}
