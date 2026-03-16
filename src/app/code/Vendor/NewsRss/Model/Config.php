<?php
declare(strict_types=1);

namespace Vendor\NewsRss\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed configuration access for the RSS widget.
 */
class Config
{
    private const XML_PATH_ENABLED = 'vendor_news_rss/general/enabled';
    private const XML_PATH_RSS_URL = 'vendor_news_rss/general/rss_url';
    private const XML_PATH_CACHE_TTL = 'vendor_news_rss/general/cache_ttl';
    private const XML_PATH_MAX_ITEMS = 'vendor_news_rss/general/max_items';
    private const DEFAULT_RSS_URL = 'https://vnexpress.net/du-lich/diem-den';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getRssUrl(): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_RSS_URL, ScopeInterface::SCOPE_STORE);

        return $value !== '' ? $value : self::DEFAULT_RSS_URL;
    }

    public function getCacheTtl(): int
    {
        $ttl = (int)$this->scopeConfig->getValue(self::XML_PATH_CACHE_TTL, ScopeInterface::SCOPE_STORE);

        return $ttl > 0 ? $ttl : 7200;
    }

    public function getMaxItems(): int
    {
        $limit = (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_ITEMS, ScopeInterface::SCOPE_STORE);

        return $limit > 0 ? $limit : 20;
    }
}
