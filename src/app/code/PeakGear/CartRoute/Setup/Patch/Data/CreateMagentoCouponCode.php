<?php
declare(strict_types=1);

namespace PeakGear\CartRoute\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class CreateMagentoCouponCode implements DataPatchInterface
{
    private const RULE_NAME = 'PeakGear Magento Coupon 300000';
    private const COUPON_CODE = 'magento';

    private ModuleDataSetupInterface $moduleDataSetup;
    private ResourceConnection $resourceConnection;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ResourceConnection $resourceConnection
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->resourceConnection = $resourceConnection;
    }

    public function apply(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $ruleTable = $this->resourceConnection->getTableName('salesrule');
        $couponTable = $this->resourceConnection->getTableName('salesrule_coupon');

        $this->moduleDataSetup->getConnection()->startSetup();

        $ruleId = (int)$connection->fetchOne(
            $connection->select()
                ->from($ruleTable, ['rule_id'])
                ->where('name = ?', self::RULE_NAME)
                ->limit(1)
        );

        if ($ruleId > 0) {
            $couponId = (int)$connection->fetchOne(
                $connection->select()
                    ->from($couponTable, ['coupon_id'])
                    ->where('code = ?', self::COUPON_CODE)
                    ->limit(1)
            );

            if ($couponId === 0) {
                $connection->insert($couponTable, [
                    'rule_id' => $ruleId,
                    'code' => self::COUPON_CODE,
                    'usage_limit' => null,
                    'usage_per_customer' => null,
                    'times_used' => 0,
                    'expiration_date' => null,
                    'is_primary' => 1,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'type' => 0,
                ]);
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies(): array
    {
        return [CreateMagentoCouponRule::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}