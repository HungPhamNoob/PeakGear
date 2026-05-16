<?php
declare(strict_types=1);

namespace PeakGear\Customer\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomerTelephoneAttribute implements DataPatchInterface
{
    private const PHONE_ATTRIBUTE = 'mobile_number';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, self::PHONE_ATTRIBUTE);

        if (!$attribute || !$attribute->getId()) {
            $customerSetup->addAttribute(Customer::ENTITY, self::PHONE_ATTRIBUTE, [
                'type' => 'varchar',
                'label' => 'Số điện thoại',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'system' => false,
                'position' => 90,
            ]);
            $customerSetup->getEavConfig()->clear();
            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, self::PHONE_ATTRIBUTE);
        }

        $attribute->setData('used_in_forms', [
            'customer_account_create',
            'customer_account_edit',
            'adminhtml_customer',
        ]);
        $attribute->setData('is_required', false);
        $attribute->save();

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
