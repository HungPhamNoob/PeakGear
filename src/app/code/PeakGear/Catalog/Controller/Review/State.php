<?php
declare(strict_types=1);

namespace PeakGear\Catalog\Controller\Review;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use PeakGear\Catalog\Model\ProductReviewService;

class State extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductReviewService $productReviewService
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true)
            ->setHeader('Pragma', 'no-cache', true)
            ->setHeader('Expires', '0', true);

        $productId = (int)$this->getRequest()->getParam('product_id');
        if ($productId <= 0) {
            return $resultJson
                ->setHttpResponseCode(400)
                ->setData([
                    'success' => false,
                    'message' => (string)__('Không tìm thấy sản phẩm cần tải đánh giá.'),
                ]);
        }

        try {
            $product = $this->loadProduct($productId);
            $state = $this->productReviewService->getReviewState($product);

            return $resultJson->setData([
                'success' => true,
                'summary' => $state['summary'],
                'reviews' => $state['reviews'],
            ]);
        } catch (LocalizedException $exception) {
            return $resultJson
                ->setHttpResponseCode(404)
                ->setData([
                    'success' => false,
                    'message' => $exception->getMessage(),
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
            throw new LocalizedException(__('Sản phẩm hiện không thể xem đánh giá.'));
        }

        return $product;
    }
}
