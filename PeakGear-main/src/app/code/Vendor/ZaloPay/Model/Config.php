<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to ZaloPay payment configuration.
 */
class Config
{
    private const XML_PATH_ACTIVE = 'payment/zalopay/active';
    private const XML_PATH_APP_ID = 'payment/zalopay/app_id';
    private const XML_PATH_KEY1 = 'payment/zalopay/key1';
    private const XML_PATH_KEY2 = 'payment/zalopay/key2';
    private const XML_PATH_API_URL = 'payment/zalopay/api_url';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isActive(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    public function getAppId(): string
    {
        $appId = (string)$this->scopeConfig->getValue(self::XML_PATH_APP_ID, ScopeInterface::SCOPE_STORE);

        return $appId !== '' ? $appId : '2553';
    }

    public function getKey1(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_KEY1, ScopeInterface::SCOPE_STORE);
    }

    public function getKey2(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_KEY2, ScopeInterface::SCOPE_STORE);
    }

    public function getApiUrl(): string
    {
        $apiUrl = (string)$this->scopeConfig->getValue(self::XML_PATH_API_URL, ScopeInterface::SCOPE_STORE);

        return $apiUrl !== '' ? $apiUrl : 'https://sb-openapi.zalopay.vn/v2/create';
    }
}
