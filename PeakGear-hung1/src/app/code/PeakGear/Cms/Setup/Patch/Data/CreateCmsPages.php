<?php
/**
 * PeakGear CMS Pages Data Patch
 * Creates About and Policies CMS pages
 */

namespace PeakGear\Cms\Setup\Patch\Data;

use Magento\Cms\Model\PageFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Model\Store;

class CreateCmsPages implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param PageFactory $pageFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        PageFactory $pageFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->pageFactory = $pageFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        // Create About page
        $aboutPage = $this->pageFactory->create();
        $aboutPage->setIdentifier('about')
            ->setTitle('Về PeakGear - Đồng Hành Cùng Đỉnh Cao')
            ->setPageLayout('1column')
            ->setStores([Store::DEFAULT_STORE_ID])
            ->setIsActive(1)
            ->setContent('<p>This content is rendered by custom template</p>')
            ->setContentHeading('Về PeakGear')
            ->setMetaKeywords('PeakGear, về chúng tôi, leo núi, outdoor')
            ->setMetaDescription('Tìm hiểu về PeakGear - thương hiệu trang bị leo núi và outdoor hàng đầu Việt Nam')
            ->save();

        // Create Policies page
        $policiesPage = $this->pageFactory->create();
        $policiesPage->setIdentifier('policies')
            ->setTitle('Chính sách & Điều khoản - PeakGear')
            ->setPageLayout('1column')
            ->setStores([Store::DEFAULT_STORE_ID])
            ->setIsActive(1)
            ->setContent('<p>This content is rendered by custom template</p>')
            ->setContentHeading('Chính sách & Điều khoản')
            ->setMetaKeywords('chính sách, điều khoản, quy định, PeakGear')
            ->setMetaDescription('Chính sách bảo mật, điều khoản sử dụng, giao hàng, đổi trả, bảo hành và thanh toán tại PeakGear')
            ->save();

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
