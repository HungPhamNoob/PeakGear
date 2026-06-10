<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Exception\InputException;
use Magento\Quote\Api\Data\AddressInterface;
use PeakGear\Customer\Model\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class CheckoutAddressPhoneValidationPluginTest extends TestCase
{
    public function testShippingAndBillingPhonesAreNormalized(): void
    {
        $shippingAddress = $this->createAddress('+84 912 345 678', '0912345678');
        $billingAddress = $this->createAddress('0987 654 321', '0987654321');
        $addressInformation = $this->createMock(ShippingInformationInterface::class);
        $addressInformation->method('getShippingAddress')->willReturn($shippingAddress);
        $addressInformation->method('getBillingAddress')->willReturn($billingAddress);

        $result = (new CheckoutAddressPhoneValidationPlugin(new PhoneNormalizer()))
            ->beforeSaveAddressInformation(
                $this->createMock(ShippingInformationManagementInterface::class),
                10,
                $addressInformation
            );

        self::assertSame([10, $addressInformation], $result);
    }

    public function testInvalidShippingPhoneIsRejected(): void
    {
        $addressInformation = $this->createMock(ShippingInformationInterface::class);
        $addressInformation->method('getShippingAddress')->willReturn($this->createAddress('12345'));
        $addressInformation->method('getBillingAddress')->willReturn(null);

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Vui lòng nhập số điện thoại hợp lệ');

        (new CheckoutAddressPhoneValidationPlugin(new PhoneNormalizer()))
            ->beforeSaveAddressInformation(
                $this->createMock(ShippingInformationManagementInterface::class),
                10,
                $addressInformation
            );
    }

    private function createAddress(string $telephone, ?string $normalized = null): AddressInterface
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getTelephone')->willReturn($telephone);

        if ($normalized !== null) {
            $address->expects(self::once())->method('setTelephone')->with($normalized);
        }

        return $address;
    }
}
