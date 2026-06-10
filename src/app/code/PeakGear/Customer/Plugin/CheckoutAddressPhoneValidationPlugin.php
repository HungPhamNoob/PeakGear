<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Exception\InputException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use PeakGear\Customer\Model\PhoneNormalizer;

class CheckoutAddressPhoneValidationPlugin
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer
    ) {
    }

    /**
     * @return array{0: mixed, 1: ShippingInformationInterface}
     * @throws InputException
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        $this->validateAndNormalize($addressInformation->getShippingAddress());
        $this->validateAndNormalize($addressInformation->getBillingAddress());

        return [$cartId, $addressInformation];
    }

    /**
     * @return array{0: mixed, 1: PaymentInterface, 2: AddressInterface|null}
     * @throws InputException
     */
    public function beforeSavePaymentInformation(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->validateAndNormalize($billingAddress);

        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * @return array{0: mixed, 1: PaymentInterface, 2: AddressInterface|null}
     * @throws InputException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->validateAndNormalize($billingAddress);

        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * @throws InputException
     */
    private function validateAndNormalize(?AddressInterface $address): void
    {
        if ($address === null) {
            return;
        }

        $telephone = (string) $address->getTelephone();
        if (!$this->phoneNormalizer->isValid($telephone)) {
            throw new InputException(__('Vui lòng nhập số điện thoại hợp lệ (VD: 0912345678).'));
        }

        $address->setTelephone($this->phoneNormalizer->normalize($telephone));
    }
}
