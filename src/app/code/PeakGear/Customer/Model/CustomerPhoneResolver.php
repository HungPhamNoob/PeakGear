<?php
declare(strict_types=1);

namespace PeakGear\Customer\Model;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class CustomerPhoneResolver
{
    private const PHONE_ATTRIBUTE = 'mobile_number';

    public function __construct(
        private readonly CollectionFactory $customerCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly EavConfig $eavConfig
    ) {
    }

    public function hasPhoneAttribute(): bool
    {
        try {
            $attribute = $this->eavConfig->getAttribute(Customer::ENTITY, self::PHONE_ATTRIBUTE);
        } catch (\Throwable) {
            return false;
        }

        return $attribute && (bool) $attribute->getId();
    }

    public function findEmailByPhone(string $phone): ?string
    {
        $normalizedPhone = $this->phoneNormalizer->normalize($phone);
        if ($normalizedPhone === '' || !$this->hasPhoneAttribute()) {
            return null;
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect(['email', self::PHONE_ATTRIBUTE])
            ->addAttributeToFilter(self::PHONE_ATTRIBUTE, $normalizedPhone)
            ->addAttributeToFilter('website_id', (int) $this->storeManager->getWebsite()->getId())
            ->setPageSize(2);

        if ($collection->getSize() > 1) {
            throw new LocalizedException(__('Số điện thoại này đang được dùng bởi nhiều tài khoản.'));
        }

        $customer = $collection->getFirstItem();
        if (!$customer->getId()) {
            return null;
        }

        return (string) $customer->getEmail();
    }

    public function phoneExists(string $phone): bool
    {
        return $this->findEmailByPhone($phone) !== null;
    }
}
