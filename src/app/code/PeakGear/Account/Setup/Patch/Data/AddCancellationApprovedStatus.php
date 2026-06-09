<?php
declare(strict_types=1);

namespace PeakGear\Account\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\StatusFactory;

class AddCancellationApprovedStatus implements DataPatchInterface
{
    public const STATUS = 'cancellation_approved';

    public function __construct(
        private readonly StatusFactory $statusFactory
    ) {
    }

    public function apply(): self
    {
        $status = $this->statusFactory->create()->load(self::STATUS);
        if (!$status->getId()) {
            $status->setStatus(self::STATUS)
                ->setLabel('Đã duyệt hủy - chờ hoàn tiền')
                ->save();
        }

        foreach ([Order::STATE_NEW, Order::STATE_PENDING_PAYMENT, Order::STATE_PROCESSING] as $state) {
            $status->assignState($state, false, true);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [AddCancellationRequestedStatus::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
