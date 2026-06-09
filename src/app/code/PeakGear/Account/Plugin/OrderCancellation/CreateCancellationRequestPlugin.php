<?php
declare(strict_types=1);

namespace PeakGear\Account\Plugin\OrderCancellation;

use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\OrderCancellation\Model\CancelOrder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PeakGear\Account\Setup\Patch\Data\AddCancellationRequestedStatus;

class CreateCancellationRequestPlugin
{
    private const PREVIOUS_STATUS_KEY = 'peakgear_status_before_cancellation_request';

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Escaper $escaper
    ) {
    }

    public function aroundExecute(CancelOrder $subject, callable $proceed, Order $order, string $reason): Order
    {
        if ($order->getStatus() === AddCancellationRequestedStatus::STATUS) {
            throw new LocalizedException(__('Yêu cầu hủy đơn này đang chờ quản trị viên duyệt.'));
        }

        $safeReason = $this->escaper->escapeHtml(trim($reason));
        $payment = $order->getPayment();
        $hasPaidAmount = ($payment && (float)$payment->getAmountPaid() > 0)
            || $order->getState() === Order::STATE_PROCESSING;
        $reviewMessage = $hasPaidAmount
            ? 'Khách hàng đã gửi yêu cầu hủy. Vui lòng duyệt yêu cầu và tạo Credit Memo/hoàn tiền trước khi đóng đơn.'
            : 'Khách hàng đã gửi yêu cầu hủy. Vui lòng kiểm tra và hủy đơn trong trang quản trị nếu chấp thuận.';

        if ($payment) {
            $payment->setAdditionalInformation(self::PREVIOUS_STATUS_KEY, (string)$order->getStatus());
        }
        $order->setStatus(AddCancellationRequestedStatus::STATUS);
        $order->addCommentToStatusHistory(
            __('Lý do khách hàng yêu cầu hủy: %1', $safeReason)->render(),
            AddCancellationRequestedStatus::STATUS,
            true
        );
        $order->addCommentToStatusHistory($reviewMessage, AddCancellationRequestedStatus::STATUS);

        return $this->orderRepository->save($order);
    }
}
