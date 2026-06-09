<?php
declare(strict_types=1);

namespace PeakGear\Account\Plugin\Adminhtml;

use Magento\Sales\Block\Adminhtml\Order\View;
use PeakGear\Account\Setup\Patch\Data\AddCancellationRequestedStatus;

class AddCancellationReviewButtons
{
    public function beforeSetLayout(View $subject): void
    {
        $order = $subject->getOrder();
        if (!$order || $order->getStatus() !== AddCancellationRequestedStatus::STATUS) {
            return;
        }

        $approveUrl = $subject->getUrl('peakgear_account/cancellation/approve', ['order_id' => $order->getId()]);
        $rejectUrl = $subject->getUrl('peakgear_account/cancellation/reject', ['order_id' => $order->getId()]);

        $subject->addButton('peakgear_cancellation_reject', [
            'label' => __('Từ chối yêu cầu hủy'),
            'class' => 'secondary',
            'onclick' => sprintf(
                "confirmSetLocation('%s', '%s')",
                __('Từ chối yêu cầu hủy đơn này?')->render(),
                $rejectUrl
            ),
        ]);
        $subject->addButton('peakgear_cancellation_approve', [
            'label' => __('Duyệt yêu cầu hủy'),
            'class' => 'primary',
            'onclick' => sprintf(
                "confirmSetLocation('%s', '%s')",
                __('Duyệt yêu cầu hủy đơn này?')->render(),
                $approveUrl
            ),
        ]);
    }
}
