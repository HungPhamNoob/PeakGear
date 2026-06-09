<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use PeakGear\FlashSale\Model\FlashSaleService;
use PHPUnit\Framework\TestCase;

class DecrementSaleQuantityTest extends TestCase
{
    public function testOnlyDiscountedOrderItemsIncrementFlashSaleSoldQty(): void
    {
        $service = $this->createMock(FlashSaleService::class);
        $discountedItem = $this->createMock(OrderItem::class);
        $regularItem = $this->createMock(OrderItem::class);
        $order = $this->createMock(Order::class);
        $observer = new Observer([
            'event' => new DataObject(['order' => $order]),
        ]);

        $order->method('getAllVisibleItems')->willReturn([$discountedItem, $regularItem]);
        $discountedItem->method('getAdditionalData')->willReturn('peakgear_flash_sale_item_id:15:2');
        $discountedItem->method('getQtyOrdered')->willReturn(3.0);
        $regularItem->method('getAdditionalData')->willReturn(FlashSaleService::REGULAR_PRICE_MARKER);
        $service->method('getMarkedItemId')->willReturnMap([
            ['peakgear_flash_sale_item_id:15:2', 15],
            [FlashSaleService::REGULAR_PRICE_MARKER, null],
        ]);
        $service->method('getMarkedDiscountQty')
            ->with('peakgear_flash_sale_item_id:15:2', 3)
            ->willReturn(2);
        $service->expects(self::once())->method('incrementSoldQty')->with(15, 2);

        (new DecrementSaleQuantity($service))->execute($observer);
    }
}
