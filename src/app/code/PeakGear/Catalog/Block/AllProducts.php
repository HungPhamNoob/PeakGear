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
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Collection|null
     */
    private $_productCollectionCache;

    public function getProductCollection()
    {
        if ($this->_productCollectionCache !== null) {
            return $this->_productCollectionCache;
        }

        $this->_productCollectionCache = $this->buildProductCollection($this->request->getParams(), true);
        return $this->_productCollectionCache;
    }

    /**
     * Build a product collection from request-like params.
     *
     * @param array $params
     * @param bool $applyPagination
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function buildProductCollection(array $params, bool $applyPagination)
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

        $categoryId = $params['category'] ?? null;
        if ($categoryId) {
            $category = $this->getCategoryById((int)$categoryId);
            if ($category !== null) {
                $collection->addCategoryFilter($category);
            }
        }

        $brandId = $params['brand'] ?? null;
        if ($brandId) {
            $collection->addAttributeToFilter('manufacturer', $brandId);
        }

        $colorId = $params['color'] ?? null;
        if ($colorId) {
            $collection->addAttributeToFilter('color', $colorId);
        }

        $keyword = trim((string)($params['q'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $collection->addAttributeToFilter([
                ['attribute' => 'name', 'like' => $like],
                ['attribute' => 'sku', 'like' => $like],
                ['attribute' => 'short_description', 'like' => $like],
            ]);
        }

        foreach ($params as $key => $value) {
            if (strpos($key, 'attr_') === 0 && $value !== '') {
                $attrCode = substr($key, 5);
                try {
                    $collection->addAttributeToFilter($attrCode, $value);
                } catch (\Exception $e) {
                    // Invalid attribute, skip
                }
            }
        }

        $priceMin = $params['price_min'] ?? null;
        $priceMax = $params['price_max'] ?? null;
        if (($priceMin !== null && $priceMin !== '') || ($priceMax !== null && $priceMax !== '')) {
            if ($priceMin !== null && $priceMin !== '' && $priceMax !== null && $priceMax !== '' && (float)$priceMin > (float)$priceMax) {
                $tmp = $priceMin;
                $priceMin = $priceMax;
                $priceMax = $tmp;
            }
            $collection->addPriceData();
            if ($priceMin !== null && $priceMin !== '') {
                $collection->getSelect()->where('price_index.final_price >= ?', (float)$priceMin);
            }
            if ($priceMax !== null && $priceMax !== '') {
                $collection->getSelect()->where('price_index.final_price <= ?', (float)$priceMax);
            }
        }

        $sort = $params['sort'] ?? 'newest';
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
            default:
                $collection->setOrder('created_at', 'DESC');
                break;
        }

        if ($applyPagination) {
            $page = (int)($params['p'] ?? 1);
            $collection->setPageSize(9);
            $collection->setCurPage($page);
        }

        return $collection;
    }

    /**
     * Return the active price range filter from the request.
     *
     * @return array{min: float|null, max: float|null}
     */
    public function getCurrentPriceFilter(): array
    {
        $min = $this->request->getParam('price_min');
        $max = $this->request->getParam('price_max');

        return [
            'min' => ($min !== null && $min !== '') ? (float)$min : null,
            'max' => ($max !== null && $max !== '') ? (float)$max : null,
        ];
    }

    /**
     * Get price range presets based on the current non-price filters.
     * Buckets are derived from actual products so we do not render empty ranges.
     *
     * @return array<int, array{min: float, max: float, label: string}>
     */
    public function getPriceRangeOptions(): array
    {
        $params = $this->request->getParams();
        unset($params['price_min'], $params['price_max'], $params['p']);

        $collection = $this->buildProductCollection($params, false);
        $items = $collection->getItems();
        if (empty($items)) {
            return [];
        }

        $prices = [];
        foreach ($items as $item) {
            $price = (float)$item->getFinalPrice();
            $prices[] = $price;
        }

        $prices = array_values(array_unique($prices));
        sort($prices, SORT_NUMERIC);

        if (count($prices) < 2) {
            return [];
        }

        $bucketCount = min(3, count($prices));
        $chunkSize = (int)ceil(count($prices) / $bucketCount);
        $chunks = array_chunk($prices, $chunkSize);

        $ranges = [];
        $totalChunks = count($chunks);
        foreach ($chunks as $index => $chunk) {
            if (empty($chunk)) {
                continue;
            }

            $rangeMin = floor((float)reset($chunk));
            $rangeMax = ceil((float)end($chunk));

            if ($totalChunks === 1) {
                $label = $this->formatPrice($rangeMin) . ' - ' . $this->formatPrice($rangeMax);
            } elseif ($index === 0) {
                $label = 'Dưới ' . $this->formatPrice($rangeMax);
            } elseif ($index === $totalChunks - 1) {
                $label = 'Trên ' . $this->formatPrice($rangeMin);
            } else {
                $label = $this->formatPrice($rangeMin) . ' - ' . $this->formatPrice($rangeMax);
            }

            $ranges[] = [
                'min' => $rangeMin,
                'max' => $rangeMax,
                'label' => $label,
            ];
        }

        return $ranges;
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getProductCollection()) {
            /** @var \Magento\Theme\Block\Html\Pager $pager */
            $pager = $this->getLayout()->createBlock(
                \Magento\Theme\Block\Html\Pager::class,
                'peakgear_allproducts_pager'
            #chỗ này để quyết định phân trang
            );
            $pager->setAvailableLimit([9 => 9])
                ->setShowPerPage(false)
                ->setCollection($this->getProductCollection());
            
            $this->setChild('pager', $pager);
            $this->getProductCollection()->load();
        }
        return $this;
    }

    public function getPagerHtml()
    {
        $collection = $this->getProductCollection();
        if (!$collection || (int)$collection->getLastPageNumber() <= 1) {
            return '';
        }
        return $this->getChildHtml('pager');
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
        $priceFilter = $this->getCurrentPriceFilter();
        return [
            'PEAKGEAR_ALL_PRODUCTS',
            $this->storeManager->getStore()->getId(),
            $this->request->getParam('category', ''),
            $this->request->getParam('sort', 'newest'),
            $this->request->getParam('brand', ''),
            $this->request->getParam('color', ''),
            $priceFilter['min'] ?? '',
            $priceFilter['max'] ?? '',
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
        $params = $this->request->getParams();
        unset($params['price_min'], $params['price_max'], $params['p']);

        $collection = $this->buildProductCollection($params, false);
        $items = $collection->getItems();

        if (empty($items)) {
            return ['min' => 0, 'max' => 1000000];
        }

        $prices = [];
        foreach ($items as $item) {
            $prices[] = (float)$item->getFinalPrice();
        }

        if (empty($prices)) {
            return ['min' => 0, 'max' => 1000000];
        }

        return [
            'min' => floor(min($prices)),
            'max' => ceil(max($prices)),
        ];
    }
    //new

    private function getCategoryAttributeValue(CategoryInterface $category, string $attributeCode): string
    {
        $attribute = $category->getCustomAttribute($attributeCode);

        return $attribute ? (string)$attribute->getValue() : '';
    }
}
