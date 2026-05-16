<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Model;

use Magento\Framework\Model\AbstractModel;

class Sale extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Sale::class);
    }
}
