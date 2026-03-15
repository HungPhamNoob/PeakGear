<?php
/**
 * PeakGear Catalog - All Products Page Controller
 * Renders the /products page showing all products with filtering
 * Supports category-specific mode with dynamic page title
 */

declare(strict_types=1);

namespace PeakGear\Catalog\Controller\Index;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;

class Index implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @param PageFactory $resultPageFactory
     * @param RequestInterface $request
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        PageFactory $resultPageFactory,
        RequestInterface $request,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->request = $request;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Execute action - render All Products page
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();

        // Check if we are in category mode
        $categoryId = $this->request->getParam('category');
        $pageTitle = 'Tất Cả Sản Phẩm';
        $pageDescription = 'Khám phá bộ sưu tập dụng cụ leo núi chuyên nghiệp, được chọn lọc kỹ lưỡng từ các thương hiệu hàng đầu thế giới.';

        if ($categoryId) {
            try {
                $category = $this->categoryRepository->get((int)$categoryId);
                $pageTitle = $category->getName();
                $description = $this->getCategoryAttributeValue($category, 'description');
                if ($description !== '') {
                    $pageDescription = strip_tags($description);
                }
            } catch (\Exception $e) {
                // Invalid category ID, fall back to defaults
            }
        }

        $resultPage->getConfig()->getTitle()->set(__($pageTitle));
        $resultPage->getConfig()->setMetaTitle(__($pageTitle . ' - PeakGear'));
        $resultPage->getConfig()->setDescription(__($pageDescription));

        // Add body class for styling
        $resultPage->getConfig()->addBodyClass('peakgear-all-products');

        return $resultPage;
    }

    private function getCategoryAttributeValue(CategoryInterface $category, string $attributeCode): string
    {
        $attribute = $category->getCustomAttribute($attributeCode);

        return $attribute ? (string)$attribute->getValue() : '';
    }
}
