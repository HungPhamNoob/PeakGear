<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Model;

use PHPUnit\Framework\TestCase;

class FlashSaleServiceTest extends TestCase
{
    public function testReachedCustomerLimitAllowsRegularPricePurchase(): void
    {
        $item = $this->createMock(Item::class);
        $service = $this->getMockBuilder(FlashSaleService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getActiveItemForProduct', 'hasReachedCustomerLimit'])
            ->getMock();

        $service->method('getActiveItemForProduct')->with(10)->willReturn($item);
        $service->method('hasReachedCustomerLimit')
            ->with(10, $item, 20, null)
            ->willReturn(true);
        $item->expects(self::never())->method('getRemainingQty');

        self::assertNull($service->validateQty(10, 5, 20));
    }

    public function testDiscountMarkerCanBeReadBack(): void
    {
        $service = $this->getMockBuilder(FlashSaleService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $marker = $service->getDiscountMarker(15, 2);

        self::assertSame(15, $service->getMarkedItemId($marker));
        self::assertSame(2, $service->getMarkedDiscountQty($marker));
        self::assertNull($service->getMarkedItemId(FlashSaleService::REGULAR_PRICE_MARKER));
    }

    public function testPerOrderOverflowUsesRegularPrice(): void
    {
        $item = $this->createMock(Item::class);
        $product = $this->createMock(\Magento\Catalog\Api\Data\ProductInterface::class);
        $service = $this->getMockBuilder(FlashSaleService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $item->method('getRemainingQty')->willReturn(20);
        $item->method('getData')->willReturnMap([
            ['max_per_order', null, 2],
            ['max_per_customer', null, 0],
            ['discount_percent', null, 20],
        ]);
        $product->method('getPrice')->willReturn(100.0);

        $discountedQty = $service->getEligibleDiscountQty(10, $item, 3);

        self::assertSame(2, $discountedQty);
        self::assertSame(86.6667, $service->getBlendedUnitPrice($product, $item, 3, $discountedQty));
    }
}
