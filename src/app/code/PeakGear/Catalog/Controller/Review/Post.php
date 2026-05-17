<?php
declare(strict_types=1);

namespace PeakGear\Catalog\Controller\Review;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use PeakGear\Catalog\Model\ProductReviewService;
use Psr\Log\LoggerInterface;

class Post extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CustomerSession $customerSession,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ReviewFactory $reviewFactory,
        private readonly RatingFactory $ratingFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductReviewService $productReviewService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $resultJson
                ->setHttpResponseCode(400)
                ->setData([
                    'success' => false,
                    'message' => (string)__('Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.'),
                ]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $resultJson
                ->setHttpResponseCode(401)
                ->setData([
                    'success' => false,
                    'requiresLogin' => true,
                    'loginUrl' => $this->productReviewService->getLoginUrl(),
                    'message' => (string)__('Bạn cần đăng nhập để đánh giá sản phẩm.'),
                ]);
        }

        $productId = (int)$this->getRequest()->getParam('product_id');
        $selectedRating = (int)$this->getRequest()->getParam('rating');
        $detail = trim((string)$this->getRequest()->getParam('detail'));

        if ($productId <= 0) {
            return $resultJson
                ->setHttpResponseCode(400)
                ->setData([
                    'success' => false,
                    'message' => (string)__('Không tìm thấy sản phẩm cần đánh giá.'),
                ]);
        }

        if ($selectedRating < 1 || $selectedRating > 5) {
            return $resultJson
                ->setHttpResponseCode(400)
                ->setData([
                    'success' => false,
                    'message' => (string)__('Vui lòng chọn số sao đánh giá.'),
                ]);
        }

        if ($detail === '') {
            return $resultJson
                ->setHttpResponseCode(400)
                ->setData([
                    'success' => false,
                    'message' => (string)__('Vui lòng nhập nội dung đánh giá.'),
                ]);
        }

        try {
            $product = $this->loadProduct($productId);
            $ratingOptions = $this->productReviewService->resolveRatingOptions($selectedRating);

            $customer = $this->customerSession->getCustomer();
            $nickname = $this->productReviewService->buildCustomerNickname(
                (string)$customer->getFirstname(),
                (string)$customer->getLastname(),
                (string)$customer->getEmail()
            );

            /** @var Review $review */
            $review = $this->reviewFactory->create();
            $review->setData([
                'title' => $this->buildTitle($detail),
                'detail' => $detail,
                'nickname' => $nickname,
            ]);
            $review->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
                ->setEntityPkValue((int)$product->getId())
                ->setStatusId(Review::STATUS_APPROVED)
                ->setCustomerId((int)$this->customerSession->getCustomerId())
                ->setStoreId((int)$this->storeManager->getStore()->getId())
                ->setStores([(int)$this->storeManager->getStore()->getId()]);

            $validationResult = $review->validate();
            if ($validationResult !== true) {
                return $resultJson
                    ->setHttpResponseCode(400)
                    ->setData([
                        'success' => false,
                        'message' => is_array($validationResult)
                            ? (string)reset($validationResult)
                            : (string)__('Không thể gửi đánh giá lúc này.'),
                    ]);
            }

            $review->save();

            foreach ($ratingOptions as $ratingId => $optionId) {
                $this->ratingFactory->create()
                    ->setRatingId($ratingId)
                    ->setReviewId($review->getId())
                    ->setCustomerId((int)$this->customerSession->getCustomerId())
                    ->addOptionVote($optionId, (int)$product->getId());
            }

            $review->aggregate();
            $state = $this->productReviewService->getReviewState($product);

            return $resultJson->setData([
                'success' => true,
                'message' => (string)__('Đánh giá của bạn đã được đăng thành công.'),
                'review' => $this->productReviewService->buildReviewPayload($review, $selectedRating),
                'summary' => $state['summary'],
            ]);
        } catch (LocalizedException $exception) {
            return $resultJson
                ->setHttpResponseCode(400)
                ->setData([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ]);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);

            return $resultJson
                ->setHttpResponseCode(500)
                ->setData([
                    'success' => false,
                    'message' => (string)__('Không thể gửi đánh giá lúc này. Vui lòng thử lại sau.'),
                ]);
        }
    }

    /**
     * @throws LocalizedException
     */
    private function loadProduct(int $productId): Product
    {
        $storeId = (int)$this->storeManager->getStore()->getId();

        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('Sản phẩm không tồn tại.'));
        }

        if (!$product instanceof Product) {
            throw new LocalizedException(__('Không thể tải dữ liệu sản phẩm.'));
        }

        if (!$product->isVisibleInCatalog() || !$product->isVisibleInSiteVisibility()) {
            throw new LocalizedException(__('Sản phẩm hiện không thể đánh giá.'));
        }

        return $product;
    }

    private function buildTitle(string $detail): string
    {
        $normalized = trim((string)preg_replace('/\s+/u', ' ', $detail));
        if ($normalized === '') {
            return (string)__('Đánh giá sản phẩm');
        }

        return mb_substr($normalized, 0, 60);
    }
}
