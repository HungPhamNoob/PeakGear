<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Framework\App\RequestInterface;
use PeakGear\Customer\Model\PhoneNormalizer;

class CustomerExtractorPlugin
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer
    ) {
    }

    public function afterExtract(
        CustomerExtractor $subject,
        CustomerInterface $customer,
        string $formCode,
        RequestInterface $request
    ): CustomerInterface {
        if ($formCode !== 'customer_account_create') {
            return $customer;
        }

        $telephone = (string) $request->getParam('telephone', '');
        if ($telephone !== '') {
            $customer->setCustomAttribute('mobile_number', $this->phoneNormalizer->normalize($telephone));
        }

        return $customer;
    }
}
