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
    // Fallback demo news
    private array $demoNews = [
        ['title' => 'Xu hướng du lịch trekking bùng nổ tại Việt Nam 2025', 'link' => 'https://vnexpress.net', 'description' => 'Ngày càng nhiều người trẻ lựa chọn trekking thay vì du lịch truyền thống...', 'pub_date' => '', 'image_url' => ''],
        ['title' => 'Thị trường đồ leo núi Việt Nam tăng trưởng 40% năm 2024', 'link' => 'https://vnexpress.net', 'description' => 'Nhu cầu sắm đồ leo núi, trekking và dã ngoại tại Việt Nam tăng mạnh...', 'pub_date' => '', 'image_url' => ''],
        ['title' => 'Fansipan - hành trình chinh phục nóc nhà Đông Dương', 'link' => 'https://vnexpress.net', 'description' => 'Chia sẻ kinh nghiệm và những điều cần chuẩn bị trước khi chinh phục Fansipan...', 'pub_date' => '', 'image_url' => ''],
        ['title' => 'Top 10 địa điểm trekking đẹp nhất miền Bắc Việt Nam', 'link' => 'https://vnexpress.net', 'description' => 'Từ Mù Cang Chải đến Tà Xùa, những cung đường trekking không thể bỏ qua...', 'pub_date' => '', 'image_url' => ''],
        ['title' => 'Hướng dẫn chọn ba lô trekking cho người mới bắt đầu', 'link' => 'https://vnexpress.net', 'description' => 'Những tiêu chí quan trọng khi chọn ba lô đi cắm trại và leo núi...', 'pub_date' => '', 'image_url' => ''],
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
    public function getNews(): array
    {
        if (!$this->config->isEnabled()) {
            return [];
        }

        $maxItems = $this->config->getMaxItems();
        $cachedItems = $this->cacheRepository->getFreshItems($this->config->getCacheTtl(), $maxItems);
        if ($cachedItems !== []) {
            return $cachedItems;
        }

        try {
            $items = $this->feedProvider->fetch($maxItems);
            $this->cacheRepository->save($items);
        } catch (\Exception $e) {
            $this->logger->warning(
                'NewsRSS remote refresh failed; using fallback stories.',
                ['exception' => $e]
            );
            $items = $this->demoNews;
        }

        return array_slice($items, 0, $maxItems);
    }
}
