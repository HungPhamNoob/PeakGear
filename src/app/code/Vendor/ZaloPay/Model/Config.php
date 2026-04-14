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
    private const XML_PATH_APP_USER = 'payment/zalopay/app_user';
    private const XML_PATH_KEY1 = 'payment/zalopay/key1';
    private const XML_PATH_KEY2 = 'payment/zalopay/key2';
    private const XML_PATH_API_URL = 'payment/zalopay/api_url';
    private const XML_PATH_QUERY_URL = 'payment/zalopay/query_url';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    private function getEnvValue(string $name): string
    {
        $value = getenv($name);

        return is_string($value) ? trim($value) : '';
    }

    public function isActive(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    public function getAppId(): string
    {
        $envValue = $this->getEnvValue('ZALOPAY_APP_ID');
        if ($envValue !== '') {
            return $envValue;
        }

        $appId = (string)$this->scopeConfig->getValue(self::XML_PATH_APP_ID, ScopeInterface::SCOPE_STORE);

        return $appId !== '' ? $appId : '554';
    }

    public function getAppUser(): string
    {
        $envValue = $this->getEnvValue('ZALOPAY_APP_USER');
        if ($envValue !== '') {
            return $envValue;
        }

        $appUser = (string)$this->scopeConfig->getValue(self::XML_PATH_APP_USER, ScopeInterface::SCOPE_STORE);

        return $appUser !== '' ? $appUser : 'user_test';
    }

    public function getKey1(): string
    {
        $envValue = $this->getEnvValue('ZALOPAY_KEY1');
        if ($envValue !== '') {
            return $envValue;
        }

        return (string)$this->scopeConfig->getValue(self::XML_PATH_KEY1, ScopeInterface::SCOPE_STORE);
    }

    public function getKey2(): string
    {
        $envValue = $this->getEnvValue('ZALOPAY_KEY2');
        if ($envValue !== '') {
            return $envValue;
        }

        return (string)$this->scopeConfig->getValue(self::XML_PATH_KEY2, ScopeInterface::SCOPE_STORE);
    }

    public function getApiUrl(): string
    {
        $envValue = $this->getEnvValue('ZALOPAY_CREATE_URL');
        if ($envValue !== '') {
            return $envValue;
        }

        $apiUrl = (string)$this->scopeConfig->getValue(self::XML_PATH_API_URL, ScopeInterface::SCOPE_STORE);

        return $apiUrl !== '' ? $apiUrl : 'https://sandbox.zalopay.com.vn/v001/tpe/createorder';
    }

    public function getQueryUrl(): string
    {
        $envValue = $this->getEnvValue('ZALOPAY_QUERY_URL');
        if ($envValue !== '') {
            return $envValue;
        }

        $queryUrl = (string)$this->scopeConfig->getValue(self::XML_PATH_QUERY_URL, ScopeInterface::SCOPE_STORE);

        return $queryUrl !== '' ? $queryUrl : 'https://sandbox.zalopay.com.vn/v001/tpe/getstatusbyapptransid';
    }

    public function isGatewayConfigured(): bool
    {
        return $this->getAppId() !== ''
            && $this->getAppUser() !== ''
            && $this->getKey1() !== ''
            && $this->getKey2() !== '';
    }
}
