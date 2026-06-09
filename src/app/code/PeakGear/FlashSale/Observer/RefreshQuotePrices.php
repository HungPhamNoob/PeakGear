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
                $hasFlashSaleMarker = $this->flashSaleService->getMarkedItemId($quoteItem->getAdditionalData())
                    || $this->flashSaleService->isRegularPriceMarker($quoteItem->getAdditionalData());
                if ($quoteItem->getOptionByCode('peakgear_flash_sale_item_id') || $hasFlashSaleMarker) {
                    $quoteItem->setCustomPrice(null);
                    $quoteItem->setOriginalCustomPrice(null);
                    $quoteItem->removeOption('peakgear_flash_sale_item_id');
                    $quoteItem->setAdditionalData(null);
                }
                continue;
            }

            $discountedQty = $this->flashSaleService->getEligibleDiscountQty(
                (int)$product->getId(),
                $item,
                (float)$quoteItem->getQty(),
                $quote->getCustomerId() ? (int)$quote->getCustomerId() : null,
                $quote->getCustomerEmail() ?: null
            );
            $price = $this->flashSaleService->getBlendedUnitPrice(
                $product,
                $item,
                (float)$quoteItem->getQty(),
                $discountedQty
            );
            $quoteItem->setCustomPrice($price);
            $quoteItem->setOriginalCustomPrice($price);
            $quoteItem->getProduct()->setIsSuperMode(true);

            if ($discountedQty <= 0) {
                $quoteItem->removeOption('peakgear_flash_sale_item_id');
                $quoteItem->setAdditionalData(FlashSaleService::REGULAR_PRICE_MARKER);
                continue;
            }

            $quoteItem->addOption([
                'code' => 'peakgear_flash_sale_item_id',
                'value' => (string)$item->getId(),
            ]);
            $quoteItem->setAdditionalData(
                $this->flashSaleService->getDiscountMarker((int)$item->getId(), $discountedQty)
            );
        }
    }
}
