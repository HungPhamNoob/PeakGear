<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Model\ResourceModel\Sale;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PeakGear\FlashSale\Model\ResourceModel\Sale as SaleResource;
use PeakGear\FlashSale\Model\Sale;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Sale::class, SaleResource::class);
    }
}
