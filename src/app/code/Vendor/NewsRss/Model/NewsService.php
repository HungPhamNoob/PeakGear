<?php
declare(strict_types=1);

namespace Vendor\NewsRss\Model;

use Vendor\NewsRss\Api\NewsFeedProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Coordinates cache reads, RSS downloads, and fallback stories.
 */
class NewsService
{
    private array $demoNews = [
        'travel' => [
            ['item_guid' => 'travel-demo-1', 'title' => 'Xu hướng du lịch trekking tại Việt Nam', 'link' => 'https://vnexpress.net/du-lich', 'description' => 'Ngày càng nhiều người trẻ lựa chọn trekking thay vì du lịch truyền thống.', 'pub_date' => '', 'image_url' => ''],
            ['item_guid' => 'travel-demo-2', 'title' => 'Fansipan - hành trình chinh phục nóc nhà Đông Dương', 'link' => 'https://vnexpress.net/du-lich', 'description' => 'Kinh nghiệm và những điều cần chuẩn bị trước khi chinh phục Fansipan.', 'pub_date' => '', 'image_url' => ''],
        ],
        'business' => [
            ['item_guid' => 'business-demo-1', 'title' => 'Tin kinh doanh mới nhất', 'link' => 'https://vnexpress.net/kinh-doanh', 'description' => 'Cập nhật thị trường, tài chính, kinh tế và doanh nghiệp.', 'pub_date' => '', 'image_url' => ''],
        ],
    ];

    public function __construct(
        private readonly Config $config,
        private readonly CacheRepository $cacheRepository,
        private readonly NewsFeedProviderInterface $feedProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getNews(string $feedCode = 'travel'): array
    {
        if (!$this->config->isEnabled()) {
            return [];
        }

        $feedCode = $feedCode === 'business' ? 'business' : 'travel';
        $maxItems = $this->config->getMaxItems();
        $cachedItems = $this->cacheRepository->getFreshItems(
            $feedCode,
            $this->config->getCacheTtl(),
            $maxItems
        );
        if ($cachedItems !== []) {
            return $cachedItems;
        }

        try {
            $items = $this->feedProvider->fetch($this->config->getFeedUrl($feedCode), $maxItems);
            $this->cacheRepository->save($feedCode, $items);
        } catch (\Exception $e) {
            $this->logger->warning(
                'NewsRSS remote refresh failed; using fallback stories.',
                ['feed_code' => $feedCode, 'exception' => $e]
            );
            $items = $this->demoNews[$feedCode];
        }

        return array_slice($items, 0, $maxItems);
    }
}
