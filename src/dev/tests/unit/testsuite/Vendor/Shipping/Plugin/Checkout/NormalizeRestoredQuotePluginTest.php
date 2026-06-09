<?php
declare(strict_types=1);

namespace Vendor\Shipping\Plugin\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vendor\Shipping\Service\QuoteRegionNormalizer;

class NormalizeRestoredQuotePluginTest extends TestCase
{
    public function testNormalizesSuccessfulRestore(): void
    {
        $quote = $this->createMock(Quote::class);
        $session = $this->createMock(CheckoutSession::class);
        $session->expects(self::once())->method('getQuote')->willReturn($quote);
        $normalizer = $this->createMock(QuoteRegionNormalizer::class);
        $normalizer->expects(self::once())->method('normalize')->with($quote);

        $plugin = new NormalizeRestoredQuotePlugin(
            $normalizer,
            $this->createMock(LoggerInterface::class)
        );

        self::assertTrue($plugin->afterRestoreQuote($session, true));
    }

    public function testSkipsFailedRestore(): void
    {
        $session = $this->createMock(CheckoutSession::class);
        $session->expects(self::never())->method('getQuote');
        $normalizer = $this->createMock(QuoteRegionNormalizer::class);
        $normalizer->expects(self::never())->method('normalize');

        $plugin = new NormalizeRestoredQuotePlugin(
            $normalizer,
            $this->createMock(LoggerInterface::class)
        );

        self::assertFalse($plugin->afterRestoreQuote($session, false));
    }
}
