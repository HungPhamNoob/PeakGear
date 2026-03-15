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
use Magento\Catalog\Helper\Category as CategoryHelper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

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

        $rootCategoryId = $this->storeManager->getStore()->getRootCategoryId();

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
                'icon' => $category->getData('category_icon') ?: self::DEFAULT_ICON,
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
}
