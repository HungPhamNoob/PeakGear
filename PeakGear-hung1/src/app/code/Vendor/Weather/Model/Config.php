<?php
declare(strict_types=1);

namespace Vendor\Weather\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed accessor for weather widget configuration.
 */
class Config
{
    private const XML_PATH_ENABLED = 'vendor_weather/general/enabled';
    private const XML_PATH_API_KEY = 'vendor_weather/general/api_key';
    private const XML_PATH_CITIES = 'vendor_weather/general/cities';
    private const XML_PATH_CACHE_TTL = 'vendor_weather/general/cache_ttl';
    private const XML_PATH_UNITS = 'vendor_weather/general/units';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getApiKey(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORE);
    }

    public function getCacheTtl(): int
    {
        $ttl = (int)$this->scopeConfig->getValue(self::XML_PATH_CACHE_TTL, ScopeInterface::SCOPE_STORE);

        return $ttl > 0 ? $ttl : 1800;
    }

    public function getUnits(): string
    {
        $units = (string)$this->scopeConfig->getValue(self::XML_PATH_UNITS, ScopeInterface::SCOPE_STORE);

        return $units !== '' ? $units : 'metric';
    }

    /**
     * @return string[]
     */
    public function getCities(): array
    {
        $configured = (string)$this->scopeConfig->getValue(self::XML_PATH_CITIES, ScopeInterface::SCOPE_STORE);
        $cities = array_values(array_filter(array_map('trim', explode(',', $configured))));

        return $cities !== [] ? $cities : [
            'Sapa',
            'Da Lat',
            'Ha Noi',
            'Ho Chi Minh City',
            'Da Nang',
            'Nha Trang',
            'Ha Giang',
            'Phu Quoc',
            'Hue',
            'Quy Nhon',
            'Can Tho',
            'Vung Tau',
        ];
    }

    public function hasConfiguredApiKey(): bool
    {
        $apiKey = $this->getApiKey();

        return $apiKey !== '' && $apiKey !== 'demo_key_replace_with_real';
    }
}
