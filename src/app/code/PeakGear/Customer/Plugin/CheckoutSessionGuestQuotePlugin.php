<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class CheckoutSessionGuestQuotePlugin
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function aroundLoadCustomerQuote(CheckoutSession $subject, callable $proceed): CheckoutSession
    {
        if (!$this->customerSession->getCustomerId()) {
            return $proceed();
        }

        $guestQuote = null;

        try {
            if ($subject->getQuoteId()) {
                $guestQuote = $subject->getQuote();
            }
        } catch (LocalizedException|\LogicException $exception) {
            $subject->clearStorage();
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to inspect checkout quote before customer quote load.', [
                'exception' => $exception,
            ]);
        }

        if ($guestQuote && $guestQuote->getId() && !(int)$guestQuote->getCustomerId()) {
            try {
                $subject->clearQuote();
                $this->cartRepository->delete($guestQuote);
            } catch (\Throwable $exception) {
                $this->logger->warning('Unable to clear guest quote before login quote load.', [
                    'quote_id' => (int)$guestQuote->getId(),
                    'exception' => $exception,
                ]);
            }
        }

        return $proceed();
    }
}
