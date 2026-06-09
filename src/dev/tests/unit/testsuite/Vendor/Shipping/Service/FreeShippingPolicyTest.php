<?php
declare(strict_types=1);

namespace Vendor\Shipping\Service;

use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\TestCase;
use Vendor\Shipping\Model\Config;

class FreeShippingPolicyTest extends TestCase
{
    /**
     * @dataProvider eligibilityProvider
     */
    public function testEligibility(bool $enabled, float $subtotal, float $threshold, bool $expected): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isFreeShippingEnabled')->with(3)->willReturn($enabled);
        $config->method('getFreeShippingSubtotal')->with(3)->willReturn($threshold);

        $request = new RateRequest();
        $request->setPackageValueWithDiscount($subtotal);

        $policy = new FreeShippingPolicy($config);

        self::assertSame($expected, $policy->isEligible($request, 3));
    }

    public static function eligibilityProvider(): array
    {
        return [
            'disabled' => [false, 2000000.0, 1000000.0, false],
            'below threshold' => [true, 999999.0, 1000000.0, false],
            'at threshold' => [true, 1000000.0, 1000000.0, true],
            'above threshold' => [true, 1500000.0, 1000000.0, true],
        ];
    }
}
