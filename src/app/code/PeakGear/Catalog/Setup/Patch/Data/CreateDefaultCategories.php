<?php
/**
 * PeakGear Catalog - Create Default Categories
 * Seeds the 6 main categories and their subcategories into Magento's catalog
 * with SVG icons, English names, and descriptions
 */

declare(strict_types=1);

namespace PeakGear\Catalog\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;

class CreateDefaultCategories implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CategoryFactory $categoryFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategoryFactory $categoryFactory,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->categoryFactory = $categoryFactory;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $rootCategoryId = (int) $this->storeManager->getStore()->getRootCategoryId();

        $categories = $this->getCategoriesData();

        foreach ($categories as $position => $catData) {
            $parentCategory = $this->createCategory(
                $catData['name'],
                $catData['url_key'],
                $rootCategoryId,
                $position + 1,
                $catData['description'],
                $catData['name_en'],
                $catData['icon']
            );

            // Create subcategories
            if (!empty($catData['subcategories'])) {
                foreach ($catData['subcategories'] as $subPosition => $subData) {
                    $this->createCategory(
                        $subData['name'],
                        $subData['url_key'],
                        (int) $parentCategory->getId(),
                        $subPosition + 1,
                        $subData['description'] ?? '',
                        $subData['name_en'] ?? '',
                        ''
                    );
                }
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Create a category
     *
     * @param string $name
     * @param string $urlKey
     * @param int $parentId
     * @param int $position
     * @param string $description
     * @param string $nameEn
     * @param string $icon
     * @return \Magento\Catalog\Model\Category
     */
    private function createCategory(
        string $name,
        string $urlKey,
        int $parentId,
        int $position,
        string $description = '',
        string $nameEn = '',
        string $icon = ''
    ) {
        $category = $this->categoryFactory->create();
        $category->setName($name);
        $category->setUrlKey($urlKey);
        $category->setParentId($parentId);
        $category->setIsActive(true);
        $category->setIncludeInMenu(true);
        $category->setPosition($position);
        $category->setStoreId(0); // All store views
        $category->setDescription($description);

        if ($nameEn) {
            $category->setData('name_en', $nameEn);
        }
        if ($icon) {
            $category->setData('category_icon', $icon);
        }

        return $this->categoryRepository->save($category);
    }

    /**
     * Get the default categories data matching the original hardcoded data
     *
     * @return array
     */
    private function getCategoriesData(): array
    {
        return [
            [
                'name' => 'Giày Leo Núi',
                'name_en' => 'Climbing Shoes',
                'url_key' => 'giay-leo-nui',
                'description' => 'Giày chuyên dụng cho mọi địa hình',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-3l2-5h9l2 4v4H3z"/><path d="M5 18v2"/><path d="M14 18v2"/><path d="M5 15h9"/><path d="M12 10V7a3 3 0 0 1 3-3h1a3 3 0 0 1 3 3v2"/><path d="M16 18h3a1 1 0 0 0 1-1v-3l-2-4h-2"/></svg>',
                'subcategories' => [
                    ['name' => 'Giày Trekking', 'url_key' => 'giay-trekking', 'name_en' => 'Trekking Shoes', 'description' => 'Giày trekking chuyên dụng'],
                    ['name' => 'Giày Leo Vách', 'url_key' => 'giay-leo-vach', 'name_en' => 'Rock Climbing Shoes', 'description' => 'Giày leo vách đá'],
                    ['name' => 'Giày Approach', 'url_key' => 'giay-approach', 'name_en' => 'Approach Shoes', 'description' => 'Giày tiếp cận địa hình'],
                    ['name' => 'Sandal Leo Núi', 'url_key' => 'sandal-leo-nui', 'name_en' => 'Hiking Sandals', 'description' => 'Sandal leo núi'],
                ],
            ],
            [
                'name' => 'Ba Lô',
                'name_en' => 'Backpacks',
                'url_key' => 'ba-lo',
                'description' => 'Ba lô chống nước, siêu bền',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20V8a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v12"/><path d="M4 20h16"/><path d="M10 6V4a2 2 0 0 1 2-2 2 2 0 0 1 2 2v2"/><path d="M8 20v-5h8v5"/><line x1="12" y1="11" x2="12" y2="15"/></svg>',
                'subcategories' => [
                    ['name' => 'Ba Lô Day Trip (20-35L)', 'url_key' => 'ba-lo-day-trip', 'name_en' => 'Day Trip (20-35L)', 'description' => 'Ba lô du lịch trong ngày'],
                    ['name' => 'Ba Lô Trekking (40-60L)', 'url_key' => 'ba-lo-trekking', 'name_en' => 'Trekking (40-60L)', 'description' => 'Ba lô trekking dài ngày'],
                    ['name' => 'Ba Lô Expedition (65L+)', 'url_key' => 'ba-lo-expedition', 'name_en' => 'Expedition (65L+)', 'description' => 'Ba lô thám hiểm'],
                    ['name' => 'Túi Đeo Chéo', 'url_key' => 'tui-deo-cheo', 'name_en' => 'Crossbody Bags', 'description' => 'Túi đeo chéo tiện lợi'],
                ],
            ],
            [
                'name' => 'Dây & Móc',
                'name_en' => 'Ropes & Carabiners',
                'url_key' => 'day-moc',
                'description' => 'Dây leo và móc an toàn',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.5 4.7 3.5 5.8L12 22l3.5-7.2C17.5 13.7 19 11.5 19 9c0-4-3-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>',
                'subcategories' => [
                    ['name' => 'Dây Dynamic', 'url_key' => 'day-dynamic', 'name_en' => 'Dynamic Ropes', 'description' => 'Dây dynamic cho leo núi'],
                    ['name' => 'Dây Static', 'url_key' => 'day-static', 'name_en' => 'Static Ropes', 'description' => 'Dây static cho rappel'],
                    ['name' => 'Móc Carabiner', 'url_key' => 'moc-carabiner', 'name_en' => 'Carabiners', 'description' => 'Móc carabiner chuyên dụng'],
                    ['name' => 'Dây Đai An Toàn', 'url_key' => 'day-dai-an-toan', 'name_en' => 'Harnesses', 'description' => 'Dây đai an toàn'],
                    ['name' => 'Thiết Bị Belay', 'url_key' => 'thiet-bi-belay', 'name_en' => 'Belay Devices', 'description' => 'Thiết bị belay'],
                ],
            ],
            [
                'name' => 'Lều & Trại',
                'name_en' => 'Tents & Camping',
                'url_key' => 'leu-trai',
                'description' => 'Lều cắm trại cao cấp',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20h20"/><path d="M5 20V9l7-7 7 7v11"/><path d="M9 20v-6h6v6"/></svg>',
                'subcategories' => [
                    ['name' => 'Lều 1-2 Người', 'url_key' => 'leu-1-2-nguoi', 'name_en' => '1-2 Person Tents', 'description' => 'Lều cho 1-2 người'],
                    ['name' => 'Lều 3-4 Người', 'url_key' => 'leu-3-4-nguoi', 'name_en' => '3-4 Person Tents', 'description' => 'Lều cho 3-4 người'],
                    ['name' => 'Túi Ngủ', 'url_key' => 'tui-ngu', 'name_en' => 'Sleeping Bags', 'description' => 'Túi ngủ giữ ấm'],
                    ['name' => 'Thảm & Đệm', 'url_key' => 'tham-dem', 'name_en' => 'Sleeping Pads', 'description' => 'Thảm và đệm cắm trại'],
                    ['name' => 'Bếp Dã Ngoại', 'url_key' => 'bep-da-ngoai', 'name_en' => 'Camp Stoves', 'description' => 'Bếp dã ngoại'],
                ],
            ],
            [
                'name' => 'Áo Khoác',
                'name_en' => 'Jackets',
                'url_key' => 'ao-khoac',
                'description' => 'Áo khoác chống gió, chống nước',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46 16 2 12 6 8 2 3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23Z"/><path d="M12 10v4"/><path d="M10 12h4"/></svg>',
                'subcategories' => [
                    ['name' => 'Áo Gió Chống Nước', 'url_key' => 'ao-gio-chong-nuoc', 'name_en' => 'Waterproof Jackets', 'description' => 'Áo gió chống nước'],
                    ['name' => 'Áo Giữ Nhiệt', 'url_key' => 'ao-giu-nhiet', 'name_en' => 'Insulated Jackets', 'description' => 'Áo giữ nhiệt'],
                    ['name' => 'Áo Lông Vũ', 'url_key' => 'ao-long-vu', 'name_en' => 'Down Jackets', 'description' => 'Áo lông vũ nhẹ ấm'],
                    ['name' => 'Áo Softshell', 'url_key' => 'ao-softshell', 'name_en' => 'Softshell Jackets', 'description' => 'Áo softshell linh hoạt'],
                ],
            ],
            [
                'name' => 'Phụ Kiện',
                'name_en' => 'Accessories',
                'url_key' => 'phu-kien',
                'description' => 'Đèn pin, la bàn, bình nước...',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
                'subcategories' => [
                    ['name' => 'Đèn Pin & Đèn Đội Đầu', 'url_key' => 'den-pin', 'name_en' => 'Flashlights & Headlamps', 'description' => 'Đèn pin và đèn đội đầu'],
                    ['name' => 'La Bàn & GPS', 'url_key' => 'la-ban-gps', 'name_en' => 'Compass & GPS', 'description' => 'La bàn và GPS'],
                    ['name' => 'Bình Nước & Túi Nước', 'url_key' => 'binh-nuoc', 'name_en' => 'Water Bottles & Bladders', 'description' => 'Bình nước và túi nước'],
                    ['name' => 'Gậy Leo Núi', 'url_key' => 'gay-leo-nui', 'name_en' => 'Trekking Poles', 'description' => 'Gậy leo núi'],
                    ['name' => 'Kính Mát', 'url_key' => 'kinh-mat', 'name_en' => 'Sunglasses', 'description' => 'Kính mát thể thao'],
                    ['name' => 'Nón Bảo Hiểm', 'url_key' => 'non-bao-hiem', 'name_en' => 'Helmets', 'description' => 'Nón bảo hiểm leo núi'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [
            AddCategoryIconAttribute::class,
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
