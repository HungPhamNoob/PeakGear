<?php
declare(strict_types=1);

namespace PeakGear\Customer\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class DisableGuestCheckout implements DataPatchInterface
{
    private const DEFAULT_SCOPE = 'default';
    private const DEFAULT_SCOPE_ID = 0;

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $configTable = $this->moduleDataSetup->getTable('core_config_data');

        $connection->insertOnDuplicate(
            $configTable,
            [
                'scope' => self::DEFAULT_SCOPE,
                'scope_id' => self::DEFAULT_SCOPE_ID,
                'path' => 'checkout/options/guest_checkout',
                'value' => '0',
            ],
            ['value']
        );

        $connection->insertOnDuplicate(
            $configTable,
            [
                'scope' => self::DEFAULT_SCOPE,
                'scope_id' => self::DEFAULT_SCOPE_ID,
                'path' => 'checkout/options/enable_guest_checkout_login',
                'value' => '0',
            ],
            ['value']
        );

        $connection->endSetup();

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
