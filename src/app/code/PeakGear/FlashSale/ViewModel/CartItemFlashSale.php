<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use PeakGear\FlashSale\Model\FlashSaleService;

class CartItemFlashSale implements ArgumentInterface
{
    public function __construct(
        private readonly FlashSaleService $flashSaleService
    ) {
    }

    public function getInfo(AbstractItem $quoteItem): ?array
    {
        $product = $quoteItem->getProduct();
        if (!$product) {
            return null;
        }

        $item = $this->flashSaleService->getActiveItemForProduct((int)$product->getId());
        if (!$item) {
            return null;
        }

        return [
            'remaining_qty' => $item->getRemainingQty(),
            'discount_percent' => (float)$item->getData('discount_percent'),
            'max_per_customer' => (int)$item->getData('max_per_customer'),
            'max_per_order' => (int)$item->getData('max_per_order'),
        ];
    }
}
