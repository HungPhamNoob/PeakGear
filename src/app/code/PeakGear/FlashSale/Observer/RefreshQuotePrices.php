<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use PeakGear\FlashSale\Model\FlashSaleService;

class RefreshQuotePrices implements ObserverInterface
{
    public function __construct(
        private readonly FlashSaleService $flashSaleService
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        if (!$quote) {
            return;
        }

        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            $product = $quoteItem->getProduct();
            if (!$product) {
                continue;
            }

            $item = $this->flashSaleService->getActiveItemForProduct((int)$product->getId());
            if (!$item) {
                if ($quoteItem->getOptionByCode('peakgear_flash_sale_item_id')) {
                    $quoteItem->setCustomPrice(null);
                    $quoteItem->setOriginalCustomPrice(null);
                    $quoteItem->removeOption('peakgear_flash_sale_item_id');
                }
                continue;
            }

            $price = $this->flashSaleService->getDiscountedPrice($product, $item);
            $quoteItem->setCustomPrice($price);
            $quoteItem->setOriginalCustomPrice($price);
            $quoteItem->addOption([
                'code' => 'peakgear_flash_sale_item_id',
                'value' => (string)$item->getId(),
            ]);
            $quoteItem->getProduct()->setIsSuperMode(true);
        }
    }
}
