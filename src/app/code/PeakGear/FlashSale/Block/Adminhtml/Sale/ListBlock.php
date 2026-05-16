<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Block\Adminhtml\Sale;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PeakGear\FlashSale\Model\ResourceModel\Sale\CollectionFactory;

class ListBlock extends Template
{
    public function __construct(
        Context $context,
        private readonly CollectionFactory $collectionFactory,
        private readonly TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSales(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('sale_id', 'DESC');
        return $collection->getItems();
    }

    public function formatStoreDateTime(?string $date): string
    {
        if (!$date) {
            return '';
        }

        try {
            $utcDate = new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
            return $utcDate
                ->setTimezone(new \DateTimeZone($this->timezone->getConfigTimezone()))
                ->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string)$date;
        }
    }
}
