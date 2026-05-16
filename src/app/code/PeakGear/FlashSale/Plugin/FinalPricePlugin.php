<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Plugin;

use Magento\Catalog\Pricing\Price\FinalPrice;
use PeakGear\FlashSale\Model\FlashSaleService;

class FinalPricePlugin
{
    public function __construct(
        private readonly FlashSaleService $flashSaleService
    ) {
    }

    public function afterGetValue(FinalPrice $subject, $result)
    {
        $product = $subject->getProduct();
        $productId = (int)$product->getId();
        if ($productId <= 0) {
            return $result;
        }

        $item = $this->flashSaleService->getActiveItemForProduct($productId);
        if (!$item) {
            return $result;
        }

        return min((float)$result, $this->flashSaleService->getDiscountedPrice($product, $item, (float)$product->getPrice()));
    }
}
