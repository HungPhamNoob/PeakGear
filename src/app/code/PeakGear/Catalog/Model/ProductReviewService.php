<?php
declare(strict_types=1);

namespace PeakGear\Catalog\Model;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Review\Model\Rating;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Store\Model\StoreManagerInterface;

class ProductReviewService
{
    private const RATING_CONFIGURATION_ERROR = 'Hệ thống đánh giá sao chưa được cấu hình đầy đủ cho storefront này.';

    /**
     * @var array<int, array<string, int|float>>
     */
    private array $summaryCache = [];

    /**
     * @var array<int, array<int, array<string, int|string>>>
     */
    private array $reviewsCache = [];

    /**
     * @var array<int, array<int, \Magento\Review\Model\Rating>>
     */
    private array $ratingCache = [];

    public function __construct(
        private readonly ReviewCollectionFactory $reviewCollectionFactory,
        private readonly RatingFactory $ratingFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        private readonly UrlInterface $urlBuilder,
        private readonly EncoderInterface $urlEncoder,
        private readonly HttpContext $httpContext,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return array{count:int,average:float,ratingSummaryPercent:int,roundedStars:int}
     */
    public function getSummary(Product $product): array
    {
        $productId = (int)$product->getId();
        if ($productId === 0) {
            return [
                'count' => 0,
                'average' => 0.0,
                'ratingSummaryPercent' => 0,
                'roundedStars' => 0,
            ];
        }

        if (isset($this->summaryCache[$productId])) {
            return $this->summaryCache[$productId];
        }

        $reviews = $this->getApprovedReviews($product);
        $reviewCount = count($reviews);
        $average = $this->calculateAverageStars($reviews);
        $ratingSummaryPercent = (int)round($average * 20);

        $this->summaryCache[$productId] = [
            'count' => $reviewCount,
            'average' => $average,
            'ratingSummaryPercent' => $ratingSummaryPercent,
            'roundedStars' => (int)round($average),
        ];

        return $this->summaryCache[$productId];
    }

    /**
     * @return array{
     *     summary: array{count:int,average:float,ratingSummaryPercent:int,roundedStars:int},
     *     reviews: array<int, array{nickname:string,date:string,stars:int,detail:string}>
     * }
     */
    public function getReviewState(Product $product): array
    {
        $reviews = $this->getApprovedReviews($product);

        return [
            'summary' => $this->getSummary($product),
            'reviews' => $reviews,
        ];
    }

    /**
     * @return array<int, array{nickname:string,date:string,stars:int,detail:string}>
     */
    public function getApprovedReviews(Product $product): array
    {
        $productId = (int)$product->getId();
        if ($productId === 0) {
            return [];
        }

        if (isset($this->reviewsCache[$productId])) {
            return $this->reviewsCache[$productId];
        }

        $storeId = (int)$this->storeManager->getStore()->getId();
        $reviews = [];

        $reviewCollection = $this->reviewCollectionFactory->create();
        $reviewCollection
            ->addStoreFilter($storeId)
            ->addStatusFilter(Review::STATUS_APPROVED)
            ->addEntityFilter(Review::ENTITY_PRODUCT_CODE, $productId)
            ->setDateOrder()
            ->addRateVotes();

        foreach ($reviewCollection as $review) {
            $reviewPayload = $this->buildReviewPayload($review);
            if ((int)$reviewPayload['stars'] <= 0) {
                continue;
            }

            $reviews[] = $reviewPayload;
        }

        $this->reviewsCache[$productId] = $reviews;

        return $this->reviewsCache[$productId];
    }

    public function isLoggedIn(): bool
    {
        return (bool)$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }

    public function getLoginUrl(): string
    {
        $refererUrl = (string)$this->request->getServer('HTTP_REFERER');
        if ($refererUrl === '') {
            $refererUrl = $this->urlBuilder->getCurrentUrl();
        }

        if (strpos($refererUrl, '#tab-reviews') === false) {
            $refererUrl .= '#tab-reviews';
        }

        $referer = $this->urlEncoder->encode($refererUrl);

        return $this->urlBuilder->getUrl(
            'customer/account/login',
            [CustomerUrl::REFERER_QUERY_PARAM_NAME => $referer]
        );
    }

    public function getPostUrl(): string
    {
        return $this->urlBuilder->getUrl('products/review/post');
    }

    public function getStateUrl(int $productId): string
    {
        return $this->urlBuilder->getUrl('products/review/state', ['product_id' => $productId]);
    }

    /**
     * @return array<int, int>
     * @throws LocalizedException
     */
    public function resolveRatingOptions(int $selectedStars): array
    {
        if ($selectedStars < 1 || $selectedStars > 5) {
            throw new LocalizedException(__('Vui lòng chọn số sao hợp lệ.'));
        }

        $ratings = $this->getActiveRatings();
        if ($ratings === []) {
            throw new LocalizedException(__(self::RATING_CONFIGURATION_ERROR));
        }

        $ratingOptions = [];
        foreach ($ratings as $rating) {
            foreach ($rating->getOptions() as $option) {
                if ((int)$option->getValue() === $selectedStars) {
                    $ratingOptions[(int)$rating->getId()] = (int)$option->getId();
                    break;
                }
            }
        }

        if ($ratingOptions === [] || count($ratingOptions) !== count($ratings)) {
            throw new LocalizedException(__(self::RATING_CONFIGURATION_ERROR));
        }

        return $ratingOptions;
    }

    public function buildCustomerNickname(string $firstName, string $lastName, string $email = ''): string
    {
        $nickname = trim(implode(' ', array_filter([$firstName, $lastName], static fn ($value): bool => $value !== '')));
        if ($nickname !== '') {
            return $nickname;
        }

        if ($email !== '') {
            $emailParts = explode('@', $email);
            if ($emailParts[0] !== '') {
                return $emailParts[0];
            }
        }

        return (string)__('Khách hàng PeakGear');
    }

    /**
     * @return array{nickname:string,date:string,stars:int,detail:string}
     */
    public function buildReviewPayload(Review $review, ?int $forcedStars = null): array
    {
        return [
            'nickname' => (string)$review->getNickname(),
            'date' => $this->formatReviewDate((string)$review->getCreatedAt()),
            'stars' => $forcedStars ?? $this->extractReviewStars($review),
            'detail' => (string)$review->getDetail(),
        ];
    }

    /**
     * @return array<int, Rating>
     */
    private function getActiveRatings(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (isset($this->ratingCache[$storeId])) {
            return $this->ratingCache[$storeId];
        }

        $ratingCollection = $this->ratingFactory->create()->getResourceCollection();
        $ratingCollection
            ->addEntityFilter(Rating::ENTITY_PRODUCT_CODE)
            ->setPositionOrder()
            ->addRatingPerStoreName($storeId)
            ->setStoreFilter($storeId)
            ->setActiveFilter(true)
            ->load()
            ->addOptionToItems();

        $ratings = [];
        foreach ($ratingCollection as $rating) {
            $ratings[] = $rating;
        }

        $this->ratingCache[$storeId] = $ratings;

        return $this->ratingCache[$storeId];
    }

    private function extractReviewStars(Review $review): int
    {
        $totalPercent = 0;
        $voteCount = 0;

        foreach ($review->getRatingVotes() as $vote) {
            $totalPercent += (int)$vote->getPercent();
            $voteCount++;
        }

        if ($voteCount === 0) {
            return 0;
        }

        return (int)round(($totalPercent / $voteCount) / 20);
    }

    private function formatReviewDate(string $createdAt): string
    {
        if ($createdAt === '') {
            return $this->timezone->date()->format('d/m/Y');
        }

        try {
            return $this->timezone->date(new \DateTime($createdAt))->format('d/m/Y');
        } catch (\Exception) {
            return $this->timezone->date()->format('d/m/Y');
        }
    }

    /**
     * @param array<int, array{nickname:string,date:string,stars:int,detail:string}> $reviews
     */
    private function calculateAverageStars(array $reviews): float
    {
        $reviewCount = count($reviews);
        if ($reviewCount === 0) {
            return 0.0;
        }

        $totalStars = 0;
        foreach ($reviews as $review) {
            $totalStars += max(0, (int)$review['stars']);
        }

        return round($totalStars / $reviewCount, 1);
    }
}
