<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Sale extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('peakgear_flash_sale', 'sale_id');
    }
}
