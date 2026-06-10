<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Exception\InputException;
use PeakGear\Customer\Model\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class AddressRepositoryPhoneValidationPluginTest extends TestCase
{
    public function testValidPhoneIsNormalizedBeforeSave(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getTelephone')->willReturn('+84 912 345 678');
        $address->expects(self::once())->method('setTelephone')->with('0912345678');

        $result = (new AddressRepositoryPhoneValidationPlugin(new PhoneNormalizer()))
            ->beforeSave($this->createMock(AddressRepositoryInterface::class), $address);

        self::assertSame([$address], $result);
    }

    public function testInvalidPhoneIsRejectedBeforeSave(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getTelephone')->willReturn('12345');

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Vui lòng nhập số điện thoại hợp lệ');

        (new AddressRepositoryPhoneValidationPlugin(new PhoneNormalizer()))
            ->beforeSave($this->createMock(AddressRepositoryInterface::class), $address);
    }
}
