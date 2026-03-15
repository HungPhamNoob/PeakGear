<?php
/**
 * PeakGear Catalog - FeaturedProducts Block
 * Provides dynamic product data for homepage featured products section
 * Fetches newest products sorted by created_at DESC
 */

declare(strict_types=1);

namespace PeakGear\Catalog\Block;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class FeaturedProducts extends AbstractProduct
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
     * @var StockHelper
     */
    private $stockHelper;

    /**
     * @var ReviewFactory
     */
    private $reviewFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var array|null
     */
    private $productsCache = null;

    /**
     * @param Context $context
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Visibility $productVisibility
     * @param StockHelper $stockHelper
     * @param ReviewFactory $reviewFactory
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param array $data
     */
    public function __construct(
        Context $context,
        ProductCollectionFactory $productCollectionFactory,
        Visibility $productVisibility,
        StockHelper $stockHelper,
        ReviewFactory $reviewFactory,
        StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency,
        CategoryRepositoryInterface $categoryRepository,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productVisibility = $productVisibility;
        $this->stockHelper = $stockHelper;
        $this->reviewFactory = $reviewFactory;
        $this->storeManager = $storeManager;
        $this->priceCurrency = $priceCurrency;
        $this->categoryRepository = $categoryRepository;
        parent::__construct($context, $data);
    }

    /**
     * Get featured products (newest products)
     *
     * @param int $limit
     * @return array
     */
    public function getFeaturedProducts(int $limit = 8): array
    {
        if ($this->productsCache !== null) {
            return $this->productsCache;
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect([
            'name', 'price', 'special_price', 'special_from_date', 'special_to_date',
            'image', 'small_image', 'thumbnail', 'url_key',
            'short_description', 'status'
        ]);
        $collection->setVisibility($this->productVisibility->getVisibleInCatalogIds());
        $collection->addStoreFilter($this->storeManager->getStore()->getId());

        // Sort by newest first (include all products regardless of stock status)
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        // Add review summary to collection
        $this->reviewFactory->create()->appendSummary($collection);

        $products = [];
        foreach ($collection as $product) {
            $products[] = $this->prepareProductData($product);
        }

        $this->productsCache = $products;
        return $this->productsCache;
    }

    /**
     * Prepare product data array for template consumption
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function prepareProductData($product): array
    {
        // Get prices
        $price = (float) $product->getFinalPrice();
        $originalPrice = (float) $product->getPrice();
        $hasSpecialPrice = $price < $originalPrice;

        // Get rating data
        $ratingSummary = $product->getRatingSummary();
        $rating = $ratingSummary ? round($ratingSummary->getRatingSummary() / 20, 1) : 0;
        $reviewCount = $ratingSummary ? (int) $ratingSummary->getReviewsCount() : 0;

        // Get stock status
        $stockItem = $product->getExtensionAttributes()
            ? $product->getExtensionAttributes()->getStockItem()
            : null;
        $inStock = $stockItem ? $stockItem->getIsInStock() : true;

        // Get category name (first category)
        $categoryName = '';
        $categoryIds = $product->getCategoryIds();
        if (!empty($categoryIds)) {
            try {
                $category = $this->categoryRepository->get((int)$categoryIds[0]);
                $categoryName = $category->getName();
            } catch (\Exception $e) {
                $categoryName = '';
            }
        }

        // Get product image URL
        $imageUrl = '';
        try {
            $imageHelper = $this->getImage($product, 'category_page_grid');
            $imageUrl = $imageHelper ? $imageHelper->getImageUrl() : '';
        } catch (\Exception $e) {
            $imageUrl = '';
        }

        return [
            'id' => (int) $product->getId(),
            'name' => $product->getName(),
            'category' => $categoryName,
            'price' => $price,
            'originalPrice' => $hasSpecialPrice ? $originalPrice : null,
            'rating' => $rating,
            'reviews' => $reviewCount,
            'inStock' => (bool) $inStock,
            'url' => $product->getProductUrl(),
            'imageUrl' => $imageUrl,
            'product' => $product, // Pass full product object for advanced use
        ];
    }

    /**
     * Format price to Vietnamese dong
     *
     * @param float $price
     * @return string
     */
    public function formatPriceVnd(float $price): string
    {
        return number_format($price, 0, ',', '.') . '₫';
    }

    /**
     * Get category initial letter for placeholder
     *
     * @param string $category
     * @return string
     */
    public function getCategoryInitial(string $category): string
    {
        if (empty($category)) {
            return 'P';
        }
        return mb_substr($category, 0, 1, 'UTF-8');
    }

    /**
     * Check if collection has products
     *
     * @return bool
     */
    public function hasProducts(): bool
    {
        return !empty($this->getFeaturedProducts());
    }

    /**
     * Get cache key info for block caching
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        return [
            'PEAKGEAR_FEATURED_PRODUCTS',
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
        return [\Magento\Catalog\Model\Product::CACHE_TAG];
    }
}
