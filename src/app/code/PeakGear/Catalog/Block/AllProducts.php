<?php
/**
 * PeakGear Catalog - AllProducts Block
 * Provides product collection with filtering/sorting for the All Products page
 * Supports category-specific mode: dynamic title, attribute-based filters, configurable product grouping
 */

declare(strict_types=1);

namespace PeakGear\Catalog\Block;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use PeakGear\Catalog\Model\CatalogFilterDataProvider;

class AllProducts extends AbstractProduct
{
    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var Visibility
     */
    private $productVisibility;

    /**
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var ReviewFactory
     */
    private $reviewFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var CatalogFilterDataProvider
     */
    private $catalogFilterDataProvider;

    /**
     * @var PriceHelper
     */
    private $priceHelper;

    /**
     * @var array|null Cached category info
     */
    private $currentCategoryInfo = null;

    /**
     * @var bool
     */
    private $categoryInfoLoaded = false;

    /**
     * @param Context $context
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Visibility $productVisibility
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ReviewFactory $reviewFactory
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param array $data
     */
    public function __construct(
        Context $context,
        ProductCollectionFactory $productCollectionFactory,
        Visibility $productVisibility,
        CategoryCollectionFactory $categoryCollectionFactory,
        ReviewFactory $reviewFactory,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        CategoryRepositoryInterface $categoryRepository,
        CatalogFilterDataProvider $catalogFilterDataProvider,
        PriceHelper $priceHelper,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productVisibility = $productVisibility;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->reviewFactory = $reviewFactory;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->categoryRepository = $categoryRepository;
        $this->catalogFilterDataProvider = $catalogFilterDataProvider;
        $this->priceHelper = $priceHelper;
        parent::__construct($context, $data);
    }

    /**
     * Check if we are in category mode (category param is set and valid)
     *
     * @return bool
     */
    public function isCategoryMode(): bool
    {
        return $this->getCurrentCategoryInfo() !== null;
    }

    /**
     * Get current category info (cached)
     *
     * @return array|null ['id' => int, 'name' => string, 'description' => string, 'url_key' => string]
     */
    public function getCurrentCategoryInfo(): ?array
    {
        if (!$this->categoryInfoLoaded) {
            $this->categoryInfoLoaded = true;
            $categoryId = $this->request->getParam('category');
            if ($categoryId) {
                $category = $this->getCategoryById((int)$categoryId);
                if ($category) {
                    $this->currentCategoryInfo = [
                        'id' => (int)$category->getId(),
                        'name' => $category->getName(),
                        'description' => $this->getCategoryAttributeValue($category, 'description'),
                        'url_key' => $this->getCategoryAttributeValue($category, 'url_key'),
                    ];
                }
            }
        }
        return $this->currentCategoryInfo;
    }

    /**
     * Get page title — category name if in category mode, else "Tất Cả Sản Phẩm"
     *
     * @return string
     */
    public function getPageTitle(): string
    {
        $catInfo = $this->getCurrentCategoryInfo();
        return $catInfo ? $catInfo['name'] : 'Tất Cả Sản Phẩm';
    }

    /**
     * Get page description for hero section
     *
     * @return string
     */
    public function getPageDescription(): string
    {
        $catInfo = $this->getCurrentCategoryInfo();
        if ($catInfo && !empty($catInfo['description'])) {
            return strip_tags($catInfo['description']);
        }
        return 'Khám phá bộ sưu tập dụng cụ leo núi chuyên nghiệp, được chọn lọc kỹ lưỡng từ các thương hiệu hàng đầu thế giới.';
    }

    /**
     * Get all products with optional filtering.
     * Only shows products visible in catalog (configurable parents, not their simple children).
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProductCollection()
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect([
            'name', 'price', 'special_price', 'special_from_date', 'special_to_date',
            'image', 'small_image', 'thumbnail', 'url_key',
            'short_description', 'status', 'manufacturer', 'color'
        ]);

        // Only show products visible in catalog/search — this naturally excludes
        // simple products that are children of configurables (they have visibility=1 "Not Visible Individually")
        $collection->setVisibility($this->productVisibility->getVisibleInCatalogIds());
        $collection->addStoreFilter($this->storeManager->getStore()->getId());

        // Apply category filter
        $categoryId = $this->request->getParam('category');
        if ($categoryId) {
            $category = $this->getCategoryById((int)$categoryId);
            if ($category !== null) {
                $collection->addCategoryFilter($category);
            }
        }

        // Apply brand (manufacturer) filter
        $brandId = $this->request->getParam('brand');
        if ($brandId) {
            $collection->addAttributeToFilter('manufacturer', $brandId);
        }

        // Apply color filter
        $colorId = $this->request->getParam('color');
        if ($colorId) {
            $collection->addAttributeToFilter('color', $colorId);
        }

        // Apply keyword search (name, sku, short_description)
        $keyword = trim((string)$this->request->getParam('q'));
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $collection->addAttributeToFilter([
                ['attribute' => 'name', 'like' => $like],
                ['attribute' => 'sku', 'like' => $like],
                ['attribute' => 'short_description', 'like' => $like],
            ]);
        }

        // Apply generic attribute filters (attr_{attribute_code}=value)
        $allParams = $this->request->getParams();
        foreach ($allParams as $key => $value) {
            if (strpos($key, 'attr_') === 0 && $value !== '') {
                $attrCode = substr($key, 5);
                try {
                    $collection->addAttributeToFilter($attrCode, $value);
                } catch (\Exception $e) {
                    // Invalid attribute, skip
                }
            }
        }

        // Apply sorting
        $sort = $this->request->getParam('sort', 'newest');
        switch ($sort) {
            case 'price_asc':
                $collection->setOrder('price', 'ASC');
                break;
            case 'price_desc':
                $collection->setOrder('price', 'DESC');
                break;
            case 'name':
                $collection->setOrder('name', 'ASC');
                break;
            default: // newest
                $collection->setOrder('created_at', 'DESC');
                break;
        }

        // Add review summary
        $this->reviewFactory->create()->appendSummary($collection);

        return $collection;
    }

    /**
     * Get category by ID
     *
     * @param int $categoryId
     * @return \Magento\Catalog\Model\Category|null
     */
    private function getCategoryById(int $categoryId)
    {
        try {
            return $this->categoryRepository->get($categoryId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get categories for sidebar filter (only when NOT in category mode)
     *
     * @return array
     */
    public function getCategories(): array
    {
        $store = $this->storeManager->getStore();
    
        // Sửa: Lấy Store Group ID từ Store, sau đó lấy Root Category ID từ Group - sửa 
        $storeGroupId = $store->getStoreGroupId();
        $rootCategoryId = $this->storeManager->getGroup($storeGroupId)->getRootCategoryId();

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'url_key', 'url_path', 'product_count', 'category_icon']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter('level', 2); // Direct children of root
        $collection->addAttributeToFilter('parent_id', $rootCategoryId);
        $collection->setOrder('position', 'ASC');

        $categories = [];
        foreach ($collection as $category) {
            $categories[] = [
                'id' => (int)$category->getId(),
                'name' => $category->getName(),
                'url' => $category->getUrl(),
                'product_count' => (int)$category->getProductCount(),
                'icon' => $category->getData('category_icon') ?: '',
            ];
        }

        return $categories;
    }

    /**
     * Get filterable attributes that actually have values among current category's products.
     * Returns attributes like color, size, etc. with their available options.
     *
     * @return array [['code' => string, 'label' => string, 'options' => [['id' => int, 'label' => string], ...]], ...]
     */
    public function getFilterableAttributes(): array
    {
        try {
            return $this->catalogFilterDataProvider->getFilterableAttributes();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get current filter value for a given attribute code
     *
     * @param string $code
     * @return int|null
     */
    public function getCurrentFilterValue(string $code): ?int
    {
        // Check direct param (e.g. color=5)
        $value = $this->request->getParam($code);
        if ($value) {
            return (int)$value;
        }
        // Check attr_ prefixed param
        $value = $this->request->getParam('attr_' . $code);
        if ($value) {
            return (int)$value;
        }
        return null;
    }

    /**
     * Get current sort order
     *
     * @return string
     */
    public function getCurrentSort(): string
    {
        return $this->request->getParam('sort', 'newest');
    }

    /**
     * Get current category filter (ID)
     *
     * @return int|null
     */
    public function getCurrentCategory(): ?int
    {
        $cat = $this->request->getParam('category');
        return $cat ? (int)$cat : null;
    }

    /**
     * Get brands (manufacturer attribute options) for filter
     *
     * @return array
     */
    public function getBrands(): array
    {
        try {
            return $this->catalogFilterDataProvider->getBrands();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get current brand filter
     *
     * @return int|null
     */
    public function getCurrentBrand(): ?int
    {
        $brand = $this->request->getParam('brand');
        return $brand ? (int)$brand : null;
    }

    /**
     * Get page URL with parameters
     *
     * @param array $params
     * @return string
     */
    public function getFilterUrl(array $params = []): string
    {
        return $this->getUrl('products', ['_query' => $params]);
    }

    /**
     * Format price for display
     *
     * @param float $price
     * @return string
     */
    public function formatPrice(float $price): string
    {
        return $this->priceHelper->currency($price, true, false);
    }

    public function getProductImageUrl(ProductInterface $product): string
    {
        return $this->getImage($product, 'category_page_grid')->getImageUrl();
    }

    /**
     * Get product count
     *
     * @return int
     */
    public function getProductCount(): int
    {
        return $this->getProductCollection()->getSize();
    }

    /**
     * Get cache key info
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        return [
            'PEAKGEAR_ALL_PRODUCTS',
            $this->storeManager->getStore()->getId(),
            $this->request->getParam('category', ''),
            $this->request->getParam('sort', 'newest'),
            $this->request->getParam('brand', ''),
            $this->request->getParam('color', ''),
            http_build_query($this->request->getParams()),
        ];
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [\Magento\Catalog\Model\Product::CACHE_TAG];
    }

    /**
     * Get the dynamic Min and Max price range for the current filtered collection
     *
     * @return array ['min' => float, 'max' => float]
     */
    public function getPriceRange(): array
    {
        $collection = clone $this->getProductCollection();
        $collection->clear();
        $collection->setPageSize(null);
        
        $minPrice = 0;
        $maxPrice = 0;
        
        // We use loaded items to correctly reflect special price, tier price etc if present
        $items = $collection->getItems();
        
        if (empty($items)) {
             return ['min' => 0, 'max' => 1000000];
        }

        $prices = [];
        foreach ($items as $item) {
            $prices[] = (float) $item->getFinalPrice();
        }

        if (!empty($prices)) {
            $minPrice = min($prices);
            $maxPrice = max($prices);
        }

        // Just to be safe, if max is still 0, default it
        if ($maxPrice == 0) {
            $maxPrice = 1000000;
        }

        return [
            'min' => floor($minPrice),
            'max' => ceil($maxPrice)
        ];
    }
    //new

    private function getCategoryAttributeValue(CategoryInterface $category, string $attributeCode): string
    {
        $attribute = $category->getCustomAttribute($attributeCode);

        return $attribute ? (string)$attribute->getValue() : '';
    }
}
