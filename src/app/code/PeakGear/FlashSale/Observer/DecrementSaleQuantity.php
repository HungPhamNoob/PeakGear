<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use PeakGear\FlashSale\Model\FlashSaleService;

class DecrementSaleQuantity implements ObserverInterface
{
    public function __construct(
        private readonly FlashSaleService $flashSaleService
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order) {
            return;
        }

        foreach ($order->getAllVisibleItems() as $orderItem) {
            $item = $this->flashSaleService->getActiveItemForProduct((int)$orderItem->getProductId());
            if (!$item) {
                continue;
            }
            $this->flashSaleService->incrementSoldQty((int)$item->getId(), (int)ceil((float)$orderItem->getQtyOrdered()));
        }
    }
}
