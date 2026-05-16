<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use PeakGear\FlashSale\Model\FlashSaleService;

class ValidateQuoteBeforeSubmit implements ObserverInterface
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

            $message = $this->flashSaleService->validateQty(
                (int)$product->getId(),
                (float)$quoteItem->getQty(),
                $quote->getCustomerId() ? (int)$quote->getCustomerId() : null,
                $quote->getCustomerEmail() ?: null
            );
            if ($message !== null) {
                throw new LocalizedException(__($message));
            }
        }
    }
}
