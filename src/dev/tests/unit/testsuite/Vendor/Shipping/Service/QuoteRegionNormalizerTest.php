<?php
declare(strict_types=1);

namespace Vendor\Shipping\Service;

use Magento\Directory\Model\ResourceModel\Region\Collection;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

class QuoteRegionNormalizerTest extends TestCase
{
    public function testNormalizesRestoredVietnamAddressesAndSavesQuote(): void
    {
        $shippingAddress = new DataObject([
            'country_id' => 'VN',
            'city' => 'Nghệ An',
            'region' => 'Hà Nội',
            'region_id' => 1,
            'region_code' => 'AL',
        ]);
        $billingAddress = new DataObject([
            'country_id' => 'VN',
            'city' => 'Nghệ An',
            'region' => 'Nghệ An',
            'region_id' => 1196,
            'region_code' => 'VN-NA',
        ]);
        $quote = $this->createMock(Quote::class);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->expects(self::once())->method('save')->with($quote);

        $normalizer = new QuoteRegionNormalizer(
            $this->createRegionCollectionFactory(),
            $cartRepository
        );

        self::assertTrue($normalizer->normalize($quote));
        self::assertSame(1196, (int)$shippingAddress->getRegionId());
        self::assertSame('Nghệ An', $shippingAddress->getRegion());
        self::assertSame('VN-NA', $shippingAddress->getRegionCode());
        self::assertSame(1196, (int)$billingAddress->getRegionId());
    }

    public function testDoesNotSaveQuoteWhenVietnamRegionsAreAlreadyValid(): void
    {
        $shippingAddress = new DataObject([
            'country_id' => 'VN',
            'city' => 'Nghệ An',
            'region' => 'Nghệ An',
            'region_id' => 1196,
            'region_code' => 'VN-NA',
        ]);
        $billingAddress = new DataObject(['country_id' => 'US']);
        $quote = $this->createMock(Quote::class);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->expects(self::never())->method('save');

        $normalizer = new QuoteRegionNormalizer(
            $this->createRegionCollectionFactory(),
            $cartRepository
        );

        self::assertFalse($normalizer->normalize($quote));
    }

    private function createRegionCollectionFactory(): CollectionFactory
    {
        $ngheAn = new DataObject([
            'region_id' => 1196,
            'name' => 'Nghệ An',
            'default_name' => 'Nghệ An',
            'code' => 'VN-NA',
        ]);
        $haNoi = new DataObject([
            'region_id' => 1180,
            'name' => 'Hà Nội',
            'default_name' => 'Hà Nội',
            'code' => 'VN-HN',
        ]);
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('addCountryFilter')->with('VN')->willReturnSelf();
        $collection->expects(self::once())->method('getItems')->willReturn([$ngheAn, $haNoi]);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($collection);

        return $factory;
    }
}
