<?php
declare(strict_types=1);

namespace PeakGear\Account\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\StatusFactory;

class AddCancellationRequestedStatus implements DataPatchInterface
{
    public const STATUS = 'cancellation_requested';

    public function __construct(
        private readonly StatusFactory $statusFactory
    ) {
    }

    public function apply(): self
    {
        $status = $this->statusFactory->create()->load(self::STATUS);
        if (!$status->getId()) {
            $status->setStatus(self::STATUS)
                ->setLabel('Chờ duyệt hủy')
                ->save();
        }

        foreach ([Order::STATE_NEW, Order::STATE_PENDING_PAYMENT, Order::STATE_PROCESSING] as $state) {
            $status->assignState($state, false, true);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
