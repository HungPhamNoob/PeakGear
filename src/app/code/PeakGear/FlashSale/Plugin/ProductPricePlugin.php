<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Plugin;

use Magento\Catalog\Model\Product;
use PeakGear\FlashSale\Model\FlashSaleService;

class ProductPricePlugin
{
    public function __construct(
        private readonly FlashSaleService $flashSaleService
    ) {
    }

    public function afterGetFinalPrice(Product $subject, float $result, $qty = null): float
    {
        $productId = (int)$subject->getId();
        if ($productId <= 0) {
            return $result;
        }

        $item = $this->flashSaleService->getActiveItemForProduct($productId);
        if (!$item) {
            return $result;
        }

        return min($result, $this->flashSaleService->getDiscountedPrice($subject, $item, (float)$subject->getPrice()));
    }
}
