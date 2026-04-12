<?php
/**
 * PeakGear CMS Pages Data Patch.
 */

declare(strict_types=1);

namespace PeakGear\Cms\Setup\Patch\Data;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class CreateCmsPages implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    private PageRepositoryInterface $pageRepository;
    private GetPageByIdentifierInterface $getPageByIdentifier;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        $pageFactory,
        PageRepositoryInterface $pageRepository,
        GetPageByIdentifierInterface $getPageByIdentifier
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->pageFactory = $pageFactory;
        $this->pageRepository = $pageRepository;
        $this->getPageByIdentifier = $getPageByIdentifier;
    }

    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        try {
            $this->upsertPage([
                'identifier' => 'about',
                'title' => 'Về PeakGear - Đồng Hành Cùng Đỉnh Cao',
                'page_layout' => '1column',
                'stores' => [Store::DEFAULT_STORE_ID],
                'is_active' => true,
                'content' => '<p>This content is rendered by custom template</p>',
                'content_heading' => 'Về PeakGear',
                'meta_keywords' => 'PeakGear, về chúng tôi, leo núi, outdoor',
                'meta_description' => 'Tìm hiểu về PeakGear - thương hiệu trang bị leo núi và outdoor hàng đầu Việt Nam'
            ]);

            $this->upsertPage([
                'identifier' => 'policies',
                'title' => 'Chính sách & Điều khoản - PeakGear',
                'page_layout' => '1column',
                'stores' => [Store::DEFAULT_STORE_ID],
                'is_active' => true,
                'content' => '<p>This content is rendered by custom template</p>',
                'content_heading' => 'Chính sách & Điều khoản',
                'meta_keywords' => 'chính sách, điều khoản, quy định, PeakGear',
                'meta_description' => 'Chính sách bảo mật, điều khoản sử dụng, giao hàng, đổi trả, bảo hành và thanh toán tại PeakGear'
            ]);
        } finally {
            $connection->endSetup();
        }
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertPage(array $data): void
    {
        /** @var Page $page */
        $page = $this->getExistingPage((string) $data['identifier']) ?? $this->pageFactory->create();

        $page->setIdentifier((string) $data['identifier']);
        $page->setTitle((string) $data['title']);
        $page->setPageLayout((string) $data['page_layout']);
        $page->setIsActive((bool) $data['is_active']);
        $page->setContent((string) $data['content']);
        $page->setContentHeading((string) $data['content_heading']);
        $page->setMetaKeywords((string) $data['meta_keywords']);
        $page->setMetaDescription((string) $data['meta_description']);
        $page->setData('store_id', (array) $data['stores']);

        $this->pageRepository->save($page);
    }

    private function getExistingPage(string $identifier): ?PageInterface
    {
        try {
            return $this->getPageByIdentifier->execute($identifier, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException $exception) {
            return null;
        }
    }
}
