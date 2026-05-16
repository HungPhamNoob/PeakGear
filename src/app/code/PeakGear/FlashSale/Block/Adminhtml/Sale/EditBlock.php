<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Block\Adminhtml\Sale;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PeakGear\FlashSale\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;
use PeakGear\FlashSale\Model\ResourceModel\Sale as SaleResource;
use PeakGear\FlashSale\Model\SaleFactory;

class EditBlock extends Template
{
    public function __construct(
        Context $context,
        private readonly SaleFactory $saleFactory,
        private readonly SaleResource $saleResource,
        private readonly ItemCollectionFactory $itemCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSale()
    {
        $sale = $this->saleFactory->create();
        $saleId = (int)$this->getRequest()->getParam('sale_id');
        if ($saleId > 0) {
            $this->saleResource->load($sale, $saleId);
        }
        return $sale;
    }

    public function getItems(int $saleId): array
    {
        if ($saleId <= 0) {
            return [];
        }

        $collection = $this->itemCollectionFactory->create();
        $collection->addFieldToFilter('sale_id', $saleId);
        $collection->setOrder('item_id', 'ASC');
        return $collection->getItems();
    }

    public function formatDateTimeInput(?string $date): string
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
            return '';
        }
    }

    public function getTimezoneLabel(): string
    {
        return $this->timezone->getConfigTimezone();
    }

    public function getProductSearchLabel(?int $productId): string
    {
        if (!$productId) {
            return '';
        }

        try {
            $product = $this->productRepository->getById($productId);
            return sprintf('#%d - %s (%s)', $productId, (string)$product->getName(), (string)$product->getSku());
        } catch (\Throwable) {
            return '#' . $productId;
        }
    }
}
