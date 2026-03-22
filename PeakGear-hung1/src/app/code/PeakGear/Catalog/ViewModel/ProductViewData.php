<?php
declare(strict_types=1);

namespace PeakGear\Catalog\ViewModel;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Provides reusable product presentation helpers for overridden theme templates.
 */
class ProductViewData implements ArgumentInterface
{
    /**
     * @var array<int, string>
     */
    private array $categoryNameCache = [];

    public function __construct(
        private readonly Image $imageHelper,
        private readonly PriceHelper $priceHelper,
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function getImageUrl(ProductInterface $product, string $imageId): string
    {
        return $this->imageHelper->init($product, $imageId)->getUrl();
    }

    public function formatPrice(float $price): string
    {
        return $this->priceHelper->currency($price, true, false);
    }

    public function getPrimaryCategoryName(ProductInterface $product): string
    {
        $categoryIds = $product->getCategoryIds();
        if ($categoryIds === []) {
            return '';
        }

        $categoryId = (int)reset($categoryIds);
        if (isset($this->categoryNameCache[$categoryId])) {
            return $this->categoryNameCache[$categoryId];
        }

        try {
            $categoryName = (string)$this->categoryRepository->get($categoryId)->getName();
        } catch (\Exception) {
            $categoryName = '';
        }

        $this->categoryNameCache[$categoryId] = $categoryName;

        return $categoryName;
    }
}
