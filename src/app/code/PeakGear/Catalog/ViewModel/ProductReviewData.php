<?php
declare(strict_types=1);

namespace PeakGear\Catalog\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use PeakGear\Catalog\Model\ProductReviewService;

class ProductReviewData implements ArgumentInterface
{
    public function __construct(
        private readonly ProductReviewService $productReviewService
    ) {
    }

    /**
     * @return array{count:int,average:float,ratingSummaryPercent:int,roundedStars:int}
     */
    public function getSummary(Product $product): array
    {
        return $this->productReviewService->getSummary($product);
    }

    /**
     * @return array<int, array{nickname:string,date:string,stars:int,detail:string}>
     */
    public function getApprovedReviews(Product $product): array
    {
        return $this->productReviewService->getApprovedReviews($product);
    }

    /**
     * @return array{
     *     summary: array{count:int,average:float,ratingSummaryPercent:int,roundedStars:int},
     *     reviews: array<int, array{nickname:string,date:string,stars:int,detail:string}>
     * }
     */
    public function getReviewState(Product $product): array
    {
        return $this->productReviewService->getReviewState($product);
    }

    public function isLoggedIn(): bool
    {
        return $this->productReviewService->isLoggedIn();
    }

    public function getLoginUrl(): string
    {
        return $this->productReviewService->getLoginUrl();
    }

    public function getPostUrl(): string
    {
        return $this->productReviewService->getPostUrl();
    }

    public function getStateUrl(int $productId): string
    {
        return $this->productReviewService->getStateUrl($productId);
    }
}
