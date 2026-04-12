<?php
declare(strict_types=1);

namespace PeakGear\CartRoute\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\SalesRule\Model\ResourceModel\Rule as RuleResource;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;

class CreateMagentoCouponRule implements DataPatchInterface
{
    private const COUPON_CODE = 'magento';
    private const RULE_NAME = 'PeakGear Magento Coupon 300000';
    private const DISCOUNT_AMOUNT = 300000;

    private ModuleDataSetupInterface $moduleDataSetup;
    private RuleFactory $ruleFactory;
    private RuleResource $ruleResource;
    private ResourceConnection $resourceConnection;
    private State $appState;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        RuleFactory $ruleFactory,
        RuleResource $ruleResource,
        ResourceConnection $resourceConnection,
        State $appState
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->ruleFactory = $ruleFactory;
        $this->ruleResource = $ruleResource;
        $this->resourceConnection = $resourceConnection;
        $this->appState = $appState;
    }

    public function apply(): void
    {
        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException $exception) {
            $this->appState->setAreaCode('adminhtml');
        }

        $this->moduleDataSetup->getConnection()->startSetup();

        $connection = $this->resourceConnection->getConnection();
        $websiteTable = $this->resourceConnection->getTableName('store_website');
        $customerGroupTable = $this->resourceConnection->getTableName('customer_group');

        $websiteIds = array_map('intval', $connection->fetchCol($connection->select()->from($websiteTable, ['website_id'])));
        $customerGroupIds = array_map('intval', $connection->fetchCol($connection->select()->from($customerGroupTable, ['customer_group_id'])));

        if (!$websiteIds || !$customerGroupIds) {
            $this->moduleDataSetup->getConnection()->endSetup();
            return;
        }

        $rule = $this->ruleFactory->create();
        $this->ruleResource->load($rule, self::COUPON_CODE, 'coupon_code');

        $rule->setName(self::RULE_NAME)
            ->setDescription('Unlimited fixed cart discount for coupon code magento.')
            ->setIsActive(1)
            ->setWebsiteIds($websiteIds)
            ->setCustomerGroupIds($customerGroupIds)
            ->setCouponType(Rule::COUPON_TYPE_SPECIFIC)
            ->setCouponCode(self::COUPON_CODE)
            ->setUsesPerCoupon(0)
            ->setUsesPerCustomer(0)
            ->setSimpleAction('cart_fixed')
            ->setDiscountAmount(self::DISCOUNT_AMOUNT)
            ->setApplyToShipping(0)
            ->setSimpleFreeShipping(0)
            ->setStopRulesProcessing(1)
            ->setSortOrder(0)
            ->setFromDate(null)
            ->setToDate(null)
            ->setIsAdvanced(1)
            ->setConditions([])
            ->setActions([]);

        $this->ruleResource->save($rule);

        $this->moduleDataSetup->getConnection()->endSetup();
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