<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Exception\InputException;
use PeakGear\Customer\Model\PhoneNormalizer;

class AddressRepositoryPhoneValidationPlugin
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer
    ) {
    }

    /**
     * @return array{0: AddressInterface}
     * @throws InputException
     */
    public function beforeSave(
        AddressRepositoryInterface $subject,
        AddressInterface $address
    ): array {
        $telephone = (string) $address->getTelephone();
        if (!$this->phoneNormalizer->isValid($telephone)) {
            throw new InputException(__('Vui lòng nhập số điện thoại hợp lệ (VD: 0912345678).'));
        }

        $address->setTelephone($this->phoneNormalizer->normalize($telephone));

        return [$address];
    }
}
