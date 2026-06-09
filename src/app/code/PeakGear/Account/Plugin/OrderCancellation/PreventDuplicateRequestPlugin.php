<?php
declare(strict_types=1);

namespace PeakGear\Account\Plugin\OrderCancellation;

use Magento\OrderCancellation\Model\CustomerCanCancel;
use Magento\Sales\Model\Order;
use PeakGear\Account\Setup\Patch\Data\AddCancellationApprovedStatus;
use PeakGear\Account\Setup\Patch\Data\AddCancellationRequestedStatus;

class PreventDuplicateRequestPlugin
{
    public function afterExecute(CustomerCanCancel $subject, bool $result, Order $order): bool
    {
        return $result && !in_array(
            $order->getStatus(),
            [AddCancellationRequestedStatus::STATUS, AddCancellationApprovedStatus::STATUS],
            true
        );
    }
}
