<?php
declare(strict_types=1);

namespace Vendor\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vendor\Shipping\Model\Config;
use Vendor\Shipping\Service\FreeShippingPolicy;
use Vendor\Shipping\Service\GhtkApi;
use Vendor\Shipping\Service\RequestPayloadBuilder;
use Vendor\Shipping\Service\WeightResolver;

class CustomShippingTest extends TestCase
{
    public function testEligibleOrderReturnsZeroRateWithoutCallingGhtk(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')
            ->with('carriers/vendor_shipping/active', 'store', null)
            ->willReturn(true);

        $request = new RateRequest();
        $request->setPackageValueWithDiscount(1000000);

        $freeShippingPolicy = $this->createMock(FreeShippingPolicy::class);
        $freeShippingPolicy->expects(self::once())
            ->method('isEligible')
            ->with($request, null)
            ->willReturn(true);

        $ghtkApi = $this->createMock(GhtkApi::class);
        $ghtkApi->expects(self::never())->method('getFee');

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnCallback(
            static fn (float $value): float => $value
        );
        $method = new Method($priceCurrency);

        $result = $this->createMock(Result::class);
        $result->expects(self::once())->method('append')->with($method);

        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturn($result);
        $methodFactory = $this->createMock(MethodFactory::class);
        $methodFactory->method('create')->willReturn($method);

        $config = $this->createMock(Config::class);
        $config->method('getTitle')->willReturn('GHTK');
        $config->method('getMethodName')->willReturn('GHTK - Tiêu chuẩn');

        $carrier = new CustomShipping(
            $scopeConfig,
            $this->createMock(ErrorFactory::class),
            $this->createMock(LoggerInterface::class),
            $resultFactory,
            $methodFactory,
            $ghtkApi,
            $config,
            $this->createMock(RequestPayloadBuilder::class),
            $this->createMock(WeightResolver::class),
            $freeShippingPolicy
        );

        self::assertSame($result, $carrier->collectRates($request));
        self::assertSame(0.0, $method->getPrice());
        self::assertSame(0.0, $method->getCost());
        self::assertSame('vendor_shipping', $method->getCarrier());
    }
}
