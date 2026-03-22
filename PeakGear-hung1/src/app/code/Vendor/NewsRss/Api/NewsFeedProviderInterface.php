<?php
declare(strict_types=1);

namespace Vendor\NewsRss\Api;

/**
 * Fetches normalized news items from the configured remote RSS feed.
 */
interface NewsFeedProviderInterface
{
    /**
     * @return list<array{item_guid:string, title:string, description:string, link:string, pub_date:string, image_url:string}>
     */
    public function fetch(int $maxItems): array;
}
