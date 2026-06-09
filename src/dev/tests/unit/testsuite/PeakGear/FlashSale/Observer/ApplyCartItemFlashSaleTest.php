<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Observer;

use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use PeakGear\FlashSale\Model\FlashSaleService;
use PeakGear\FlashSale\Model\Item;
use PHPUnit\Framework\TestCase;

class ApplyCartItemFlashSaleTest extends TestCase
{
    public function testPerOrderOverflowUsesBlendedPrice(): void
    {
        $service = $this->createMock(FlashSaleService::class);
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $quoteItem = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParentItem', 'getProduct', 'getQty', 'setCustomPrice', 'addOption'])
            ->addMethods(['setOriginalCustomPrice', 'setAdditionalData'])
            ->getMock();
        $product = $this->createMock(Product::class);
        $saleItem = $this->createMock(Item::class);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCustomerId', 'getCustomerEmail'])
            ->getMock();
        $observer = new Observer([
            'event' => new DataObject(['quote_item' => $quoteItem]),
        ]);

        $quoteItem->method('getParentItem')->willReturn(null);
        $quoteItem->method('getProduct')->willReturn($product);
        $quoteItem->method('getQty')->willReturn(3.0);
        $product->method('getId')->willReturn(10);
        $saleItem->method('getId')->willReturn(15);
        $service->method('getActiveItemForProduct')->with(10)->willReturn($saleItem);
        $checkoutSession->method('getQuote')->willReturn($quote);
        $quote->method('getCustomerId')->willReturn(20);
        $quote->method('getCustomerEmail')->willReturn(null);
        $service->method('getEligibleDiscountQty')
            ->with(10, $saleItem, 3.0, 20, null)
            ->willReturn(2);
        $service->method('getBlendedUnitPrice')
            ->with($product, $saleItem, 3.0, 2)
            ->willReturn(200000.0);
        $service->method('getDiscountMarker')->with(15, 2)->willReturn('peakgear_flash_sale_item_id:15:2');

        $quoteItem->expects(self::once())->method('setCustomPrice')->with(200000.0)->willReturnSelf();
        $quoteItem->expects(self::once())->method('setOriginalCustomPrice')->with(200000.0)->willReturnSelf();
        $quoteItem->expects(self::once())->method('addOption');
        $quoteItem->expects(self::once())
            ->method('setAdditionalData')
            ->with('peakgear_flash_sale_item_id:15:2')
            ->willReturnSelf();

        (new ApplyCartItemFlashSale(
            $service,
            $checkoutSession
        ))->execute($observer);
    }
}
