<?php
/**
 * PeakGear Catalog - CategoryList Block
 * Provides dynamic category data for header navigation, homepage, and footer
 */

declare(strict_types=1);

namespace PeakGear\Catalog\Block;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

use Magento\Catalog\Helper\Category as CategoryHelper;

class CategoryList extends Template
{
    /**
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var Visibility
     */
    private $productVisibility;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var array|null
     */
    private $categoriesCache = null;

    /**
     * Default SVG icon used when category has no custom icon
     */
    private const DEFAULT_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>';

    /**
     * @param Context $context
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Visibility $productVisibility
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        CategoryCollectionFactory $categoryCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        Visibility $productVisibility,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productVisibility = $productVisibility;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Get top-level categories with their children (subcategories)
     * Returns array of category data for use in templates
     *
     * @return array
     */
    public function getCategories(): array
    {
        if ($this->categoriesCache !== null) {
            return $this->categoriesCache;
        }

        // Use the default catalog root category
        $store = $this->storeManager->getStore();
        $storeGroupId = $store->getStoreGroupId();
        $rootCategoryId = $this->storeManager->getGroup($storeGroupId)->getRootCategoryId();

        // Get top-level categories (direct children of root)
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'url_key', 'url_path', 'description', 'image', 'category_icon', 'name_en', 'is_active'])
            ->addFieldToFilter('parent_id', $rootCategoryId)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('include_in_menu', 1)
            ->setOrder('position', 'ASC');

        $categories = [];
        foreach ($collection as $category) {
            $categoryData = [
                'id' => (int) $category->getId(),
                'name' => $category->getName(),
                'nameEn' => $category->getData('name_en') ?: '',
                'slug' => $category->getUrlKey(),
                'url' => $category->getUrl(),
                'description' => $this->getCategoryAttributeValue($category, 'description'),
                'image' => $category->getImageUrl() ?: '',
                'icon' => $category->getData('category_icon') ?: $this->getIconByCategoryName($category->getName()),
                'count' => $this->getProductCount($category),
                'subcategories' => $this->getSubcategories($category),
            ];
            $categories[] = $categoryData;
        }

        $this->categoriesCache = $categories;
        return $this->categoriesCache;
    }

    /**
     * Get categories for footer (limited number)
     *
     * @param int $limit
     * @return array
     */
    public function getFooterCategories(int $limit = 6): array
    {
        $categories = $this->getCategories();
        return array_slice($categories, 0, $limit);
    }

    /**
     * Get subcategories for a parent category
     *
     * @param Category $parentCategory
     * @return array
     */
    private function getSubcategories(Category $parentCategory): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'url_key', 'url_path', 'is_active'])
            ->addFieldToFilter('parent_id', $parentCategory->getId())
            ->addFieldToFilter('is_active', 1)
            ->setOrder('position', 'ASC');

        $subcategories = [];
        foreach ($collection as $subcat) {
            $subcategories[] = [
                'name' => $subcat->getName(),
                'slug' => $subcat->getUrlKey(),
                'url' => $subcat->getUrl(),
            ];
        }

        return $subcategories;
    }

    /**
     * Get count of visible products for a category.
     * Only counts products with visibility "Catalog", "Search", or "Catalog, Search".
     * This means configurable parents count as 1, their invisible simple children don't count.
     *
     * @param Category $category
     * @return int
     */
    private function getProductCount(Category $category): int
    {
        try {
            $productCollection = $this->productCollectionFactory->create();
            $productCollection->addCategoryFilter($category);
            $productCollection->setVisibility($this->productVisibility->getVisibleInSiteIds());
            return $productCollection->getSize();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache key info for block caching
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        return [
            'PEAKGEAR_CATEGORY_LIST',
            $this->storeManager->getStore()->getId(),
            $this->_design->getDesignTheme()->getId(),
            'template' => $this->getTemplate(),
        ];
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [\Magento\Catalog\Model\Category::CACHE_TAG];
    }

    private function getCategoryAttributeValue(CategoryInterface $category, string $attributeCode): string
    {
        $attribute = $category->getCustomAttribute($attributeCode);

        return $attribute ? (string)$attribute->getValue() : '';
    }

    /**
     * Return a keyword-matched SVG icon for a Vietnamese category name.
     * Falls back to DEFAULT_ICON if no keyword matches.
     */
    private function getIconByCategoryName(string $name): string
    {
        $n = mb_strtolower($name);

        // Backpack / Bag
        if (str_contains($n, 'ba lo') || str_contains($n, 'ba lô') || str_contains($n, 'tui xach') || str_contains($n, 'túi xách')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2h6a2 2 0 0 1 2 2v1H7V4a2 2 0 0 1 2-2z"/><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>';
        }

        // Boot / Shoe / Sandal
        if (str_contains($n, 'giày') || str_contains($n, 'giay') || str_contains($n, 'sandal') || str_contains($n, 'dép') || str_contains($n, 'dep')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18V9l3-5h5.5l2 3v3H21v4l-2 4H4z"/><path d="M2 18h20"/><path d="M9 4v6"/></svg>';
        }

        // Tent / Camp / Bivouac
        if (str_contains($n, 'lều') || str_contains($n, 'leu') || str_contains($n, 'trại') || str_contains($n, 'trai') || str_contains($n, 'camp') || str_contains($n, 'bivouac')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3L2 21h20L12 3z"/><path d="M10 21v-5a2 2 0 0 1 4 0v5"/></svg>';
        }

        // Rope / Hook / Carabiner
        if (str_contains($n, 'dây') || str_contains($n, 'day') || str_contains($n, 'móc') || str_contains($n, 'moc')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12a3 3 0 1 0 6 0V8a3 3 0 0 0-6 0v4z"/><path d="M12 15v6"/><path d="M9 19l3 2 3-2"/></svg>';
        }

        // Climbing Tools / Equipment
        if (str_contains($n, 'dụng cụ') || str_contains($n, 'dung cu') || str_contains($n, 'leo núi') || str_contains($n, 'leo nui')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>';
        }

        // Jacket / Coat
        if (str_contains($n, 'áo khoác') || str_contains($n, 'ao khoac')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 6l4-2 2 4-2 1v10h12V9l-2-1 2-4 4 2-3 14H5L2 6z"/><path d="M12 7v14"/></svg>';
        }

        // Clothing / Apparel
        if (str_contains($n, 'quần áo') || str_contains($n, 'quan ao') || str_contains($n, 'áo') || str_contains($n, 'quần') || str_contains($n, 'quan ')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7l4-3 3 4-3 1v12h12V9L15 4l3 3-3 3"/><path d="M12 8v13"/></svg>';
        }

        // Safety / First Aid
        if (str_contains($n, 'an toàn') || str_contains($n, 'an toan') || str_contains($n, 'sơ cứu') || str_contains($n, 'so cuu')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';
        }

        // Cooking / Kitchen
        if (str_contains($n, 'nấu ăn') || str_contains($n, 'nau an') || str_contains($n, 'thiết bị nấu') || str_contains($n, 'bếp') || str_contains($n, 'bep')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a5 5 0 0 0-5 5v3H5a1 1 0 0 0-1 1v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a1 1 0 0 0-1-1h-2V7a5 5 0 0 0-5-5z"/><path d="M9 7h6"/></svg>';
        }

        // Equipment / Technology / Devices
        if (str_contains($n, 'thiết bị') || str_contains($n, 'thiet bi')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>';
        }

        // Accessories
        if (str_contains($n, 'phụ kiện') || str_contains($n, 'phu kien')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        }

        return self::DEFAULT_ICON;
    }
}
