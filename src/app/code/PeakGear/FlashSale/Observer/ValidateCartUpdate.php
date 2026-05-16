<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use PeakGear\FlashSale\Model\FlashSaleService;

class ValidateCartUpdate implements ObserverInterface
{
    public function __construct(
        private readonly FlashSaleService $flashSaleService,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function execute(Observer $observer): void
    {
        $info = (array)$observer->getEvent()->getData('info');
        if (empty($info)) {
            return;
        }

        $quote = $this->checkoutSession->getQuote();
        foreach ($info as $itemId => $row) {
            $quoteItem = $quote->getItemById((int)$itemId);
            if (!$quoteItem || !is_array($row)) {
                continue;
            }

            $product = $quoteItem->getProduct();
            $message = $this->flashSaleService->validateQty(
                (int)$product->getId(),
                (float)($row['qty'] ?? $quoteItem->getQty()),
                $quote->getCustomerId() ? (int)$quote->getCustomerId() : null,
                $quote->getCustomerEmail() ?: null
            );
            if ($message !== null) {
                throw new LocalizedException(__($message));
            }
        }
    }
}
