<?php
declare(strict_types=1);

namespace PeakGear\Customer\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class BackfillCustomerMobileNumber implements DataPatchInterface
{
    private const PHONE_ATTRIBUTE = 'mobile_number';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $entityTypeId = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->moduleDataSetup->getTable('eav_entity_type'), 'entity_type_id')
                ->where('entity_type_code = ?', Customer::ENTITY)
        );
        $attributeId = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->moduleDataSetup->getTable('eav_attribute'), 'attribute_id')
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('attribute_code = ?', self::PHONE_ATTRIBUTE)
        );

        if ($attributeId) {
            $this->backfillMobileNumber($connection, $attributeId);
        }

        $connection->endSetup();

        return $this;
    }

    private function backfillMobileNumber($connection, int $attributeId): void
    {
        $customerTable = $this->moduleDataSetup->getTable('customer_entity');
        $valueTable = $this->moduleDataSetup->getTable('customer_entity_varchar');

        $customers = $connection->fetchAll(
            $connection->select()
                ->from(['customer' => $customerTable], ['entity_id', 'email'])
                ->joinLeft(
                    ['mobile' => $valueTable],
                    'mobile.entity_id = customer.entity_id AND mobile.attribute_id = ' . $attributeId,
                    ['mobile_number' => 'value']
                )
                ->where('customer.email LIKE ?', 'phone-%@peakgear.local')
        );

        foreach ($customers as $customer) {
            if ((string) ($customer['mobile_number'] ?? '') !== '') {
                continue;
            }

            if (!preg_match('/^phone-([0-9]+)@peakgear\.local$/', (string) $customer['email'], $matches)) {
                continue;
            }

            $connection->insertOnDuplicate(
                $valueTable,
                [
                    'attribute_id' => $attributeId,
                    'entity_id' => (int) $customer['entity_id'],
                    'value' => $matches[1],
                ],
                ['value']
            );
        }
    }

    public static function getDependencies(): array
    {
        return [
            AddCustomerTelephoneAttribute::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
