<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use PeakGear\FlashSale\Model\FlashSaleService;

class ApplyCartItemFlashSale implements ObserverInterface
{
    public function __construct(
        private readonly FlashSaleService $flashSaleService,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quoteItem = $observer->getEvent()->getData('quote_item');
        if (!$quoteItem || !$quoteItem->getProduct()) {
            return;
        }

        $quoteItem = $quoteItem->getParentItem() ?: $quoteItem;
        $product = $quoteItem->getProduct();
        $item = $this->flashSaleService->getActiveItemForProduct((int)$product->getId());
        if (!$item) {
            return;
        }

        $quote = $this->checkoutSession->getQuote();
        $customerId = $quote->getCustomerId() ? (int)$quote->getCustomerId() : null;
        $customerEmail = $quote->getCustomerEmail() ?: null;

        $discountedQty = $this->flashSaleService->getEligibleDiscountQty(
            (int)$product->getId(),
            $item,
            (float)$quoteItem->getQty(),
            $customerId,
            $customerEmail
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
            return;
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
