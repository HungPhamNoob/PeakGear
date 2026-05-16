<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Model;

use Magento\Framework\Model\AbstractModel;

class Item extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Item::class);
    }

    public function getRemainingQty(): int
    {
        return max(0, (int)$this->getData('qty_limit') - (int)$this->getData('sold_qty'));
    }
}
