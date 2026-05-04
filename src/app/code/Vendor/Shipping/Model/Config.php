<?php
declare(strict_types=1);

namespace Vendor\Shipping\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_BASE = 'carriers/vendor_shipping/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getTitle(?int $storeId = null): string
    {
        return $this->getValue('title', $storeId, 'GHTK Shipping');
    }

    public function getMethodName(?int $storeId = null): string
    {
        return $this->getValue('name', $storeId, 'Standard Delivery');
    }

    public function getApiToken(?int $storeId = null): string
    {
        return $this->getValue('api_token', $storeId);
    }

    public function getClientSource(?int $storeId = null): string
    {
        return $this->getValue('client_source', $storeId);
    }

    public function isSandboxMode(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_BASE . 'sandbox_mode', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getPickAddressId(?int $storeId = null): string
    {
        return $this->getValue('pick_address_id', $storeId);
    }

    public function getPickAddress(?int $storeId = null): string
    {
        return $this->getValue('pick_address', $storeId);
    }

    public function getPickProvince(?int $storeId = null): string
    {
        return $this->getValue('pick_province', $storeId);
    }

    public function getPickDistrict(?int $storeId = null): string
    {
        return $this->getValue('pick_district', $storeId);
    }

    public function getPickWard(?int $storeId = null): string
    {
        return $this->getValue('pick_ward', $storeId);
    }

    public function getTransport(?int $storeId = null): string
    {
        return $this->getValue('transport', $storeId, 'road');
    }

    public function getWeightUnit(?int $storeId = null): string
    {
        $unit = strtolower($this->getValue('weight_unit', $storeId, 'kg'));

        return in_array($unit, ['g', 'kg'], true) ? $unit : 'kg';
    }

    public function getDefaultWeightGram(?int $storeId = null): int
    {
        $weight = (int)$this->getValue('default_weight_gram', $storeId, '500');

        return max(1, $weight);
    }

    public function isFallbackEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_BASE . 'fallback_enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getFallbackPrice(?int $storeId = null): float
    {
        return (float)$this->getValue('fallback_price', $storeId, '500000');
    }

    public function getCacheTtl(?int $storeId = null): int
    {
        $ttl = (int)$this->getValue('cache_ttl', $storeId, '300');

        return max(0, $ttl);
    }

    public function getTimeout(?int $storeId = null): int
    {
        $timeout = (int)$this->getValue('timeout', $storeId, '10');

        return max(1, $timeout);
    }

    public function isDebug(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_BASE . 'debug', ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getValue(string $field, ?int $storeId = null, string $default = ''): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_BASE . $field, ScopeInterface::SCOPE_STORE, $storeId);

        return $value !== '' ? $value : $default;
    }
}
