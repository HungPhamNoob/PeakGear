<?php
declare(strict_types=1);

namespace Vendor\CurrencyRate\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed reader for currency-rate module configuration values.
 */
class Config
{
    private const XML_PATH_ENABLED = 'vendor_currency_rate/general/enabled';
    private const XML_PATH_FEED_URL = 'vendor_currency_rate/general/xml_url';
    private const XML_PATH_CACHE_TTL = 'vendor_currency_rate/general/cache_ttl';
    private const XML_PATH_CURRENCIES = 'vendor_currency_rate/general/currencies';
    private const DEFAULT_FEED_URL = 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=68';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getFeedUrl(): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_FEED_URL, ScopeInterface::SCOPE_STORE);

        return $value !== '' ? $value : self::DEFAULT_FEED_URL;
    }

    public function getCacheTtl(): int
    {
        $ttl = (int)$this->scopeConfig->getValue(self::XML_PATH_CACHE_TTL, ScopeInterface::SCOPE_STORE);

        return $ttl > 0 ? $ttl : 3600;
    }

    /**
     * @return string[]
     */
    public function getTrackedCurrencies(): array
    {
        $configured = (string)$this->scopeConfig->getValue(self::XML_PATH_CURRENCIES, ScopeInterface::SCOPE_STORE);
        $codes = array_map('trim', explode(',', $configured));
        $codes = array_filter(array_map('strtoupper', $codes));

        return $codes !== [] ? array_values(array_unique($codes)) : ['USD', 'EUR', 'JPY', 'CNY', 'GBP', 'AUD', 'KRW', 'SGD'];
    }
}
