<?php
declare(strict_types=1);

namespace Vendor\NewsRss\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Stores normalized RSS items in a compact cache table.
 */
class CacheRepository
{
    private const TABLE_NAME = 'vendor_news_rss_cache';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFreshItems(int $ttl, int $maxItems): array
    {
        return $this->getConnection()->fetchAll(
            $this->getConnection()->select()
                ->from($this->getTableName())
                ->where('cached_at >= ?', $this->dateTime->gmtDate('Y-m-d H:i:s', time() - $ttl))
                ->order('pub_date DESC')
                ->limit($maxItems)
        );
    }

    /**
     * @param list<array{item_guid:string, title:string, description:string, link:string, pub_date:string, image_url:string}> $items
     */
    public function save(array $items): void
    {
        $connection = $this->getConnection();
        $timestamp = $this->dateTime->gmtDate('Y-m-d H:i:s');

        $connection->delete($this->getTableName());
        foreach ($items as $item) {
            $connection->insertOnDuplicate(
                $this->getTableName(),
                [
                    'item_guid' => $item['item_guid'],
                    'title' => mb_substr($item['title'], 0, 500),
                    'description' => $item['description'],
                    'link' => $item['link'],
                    'pub_date' => $item['pub_date'] !== '' ? $item['pub_date'] : null,
                    'image_url' => $item['image_url'],
                    'cached_at' => $timestamp,
                ],
                ['title', 'description', 'link', 'pub_date', 'image_url', 'cached_at']
            );
        }
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function getTableName(): string
    {
        return $this->resourceConnection->getTableName(self::TABLE_NAME);
    }
}
