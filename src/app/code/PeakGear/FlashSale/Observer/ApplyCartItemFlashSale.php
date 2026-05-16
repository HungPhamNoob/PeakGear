<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use PeakGear\FlashSale\Model\FlashSaleService;

class ApplyCartItemFlashSale implements ObserverInterface
{
    public function __construct(
        private readonly FlashSaleService $flashSaleService,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ManagerInterface $messageManager
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
        $message = $this->flashSaleService->validateQty(
            (int)$product->getId(),
            (float)$quoteItem->getQty(),
            $quote->getCustomerId() ? (int)$quote->getCustomerId() : null,
            $quote->getCustomerEmail() ?: null
        );
        if ($message !== null) {
            $quote->removeItem((int)$quoteItem->getId());
            $this->cartRepository->save($quote);
            $this->messageManager->addErrorMessage($message);
            throw new LocalizedException(__($message));
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
