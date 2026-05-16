<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Model\ResourceModel\Item;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PeakGear\FlashSale\Model\Item;
use PeakGear\FlashSale\Model\ResourceModel\Item as ItemResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Item::class, ItemResource::class);
    }
}
