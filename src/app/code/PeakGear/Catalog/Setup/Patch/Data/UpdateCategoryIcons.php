<?php
/**
 * PeakGear Catalog - Update Category Icons
 * Updates SVG icons for existing categories to use improved, more recognizable icons
 * This patch runs after CreateDefaultCategories and updates the category_icon attribute
 */

declare(strict_types=1);

namespace PeakGear\Catalog\Setup\Patch\Data;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateCategoryIcons implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var CollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * Map of url_key => new SVG icon
     */
    private const ICON_MAP = [
        'giay-leo-nui' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-3l2-5h9l2 4v4H3z"/><path d="M5 18v2"/><path d="M14 18v2"/><path d="M5 15h9"/><path d="M12 10V7a3 3 0 0 1 3-3h1a3 3 0 0 1 3 3v2"/><path d="M16 18h3a1 1 0 0 0 1-1v-3l-2-4h-2"/></svg>',
        'ba-lo'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20V8a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v12"/><path d="M4 20h16"/><path d="M10 6V4a2 2 0 0 1 2-2 2 2 0 0 1 2 2v2"/><path d="M8 20v-5h8v5"/><line x1="12" y1="11" x2="12" y2="15"/></svg>',
        'day-moc'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.5 4.7 3.5 5.8L12 22l3.5-7.2C17.5 13.7 19 11.5 19 9c0-4-3-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>',
        'leu-trai'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20h20"/><path d="M5 20V9l7-7 7 7v11"/><path d="M9 20v-6h6v6"/></svg>',
        'ao-khoac'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46 16 2 12 6 8 2 3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23Z"/><path d="M12 10v4"/><path d="M10 12h4"/></svg>',
        'phu-kien'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    ];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CollectionFactory $categoryCollectionFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['url_key', 'category_icon'])
            ->addAttributeToFilter('level', 2)
            ->addAttributeToFilter('is_active', 1);

        foreach ($collection as $category) {
            $urlKey = $category->getUrlKey();
            if (isset(self::ICON_MAP[$urlKey])) {
                $category->setData('category_icon', self::ICON_MAP[$urlKey]);
                $category->getResource()->saveAttribute($category, 'category_icon');
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [
            CreateDefaultCategories::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
