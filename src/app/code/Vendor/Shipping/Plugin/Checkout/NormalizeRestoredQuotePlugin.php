<?php
declare(strict_types=1);

namespace Vendor\Shipping\Plugin\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Psr\Log\LoggerInterface;
use Vendor\Shipping\Service\QuoteRegionNormalizer;

class NormalizeRestoredQuotePlugin
{
    public function __construct(
        private readonly QuoteRegionNormalizer $quoteRegionNormalizer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function afterRestoreQuote(CheckoutSession $subject, bool $result): bool
    {
        if (!$result) {
            return false;
        }

        try {
            $this->quoteRegionNormalizer->normalize($subject->getQuote());
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to normalize restored quote regions.', [
                'quote_id' => $subject->getQuoteId(),
                'exception' => $exception,
            ]);
        }

        return true;
    }
}
