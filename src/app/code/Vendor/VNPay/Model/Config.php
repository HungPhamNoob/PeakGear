<?php
declare(strict_types=1);

namespace Vendor\VNPay\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to VNPay configuration values.
 */
class Config
{
    private const XML_PATH_ACTIVE = 'payment/vnpay/active';
    private const XML_PATH_TMN_CODE = 'payment/vnpay/tmn_code';
    private const XML_PATH_HASH_SECRET = 'payment/vnpay/hash_secret';
    private const XML_PATH_GATEWAY_URL = 'payment/vnpay/vnp_url';
    private const XML_PATH_RETURN_PATH = 'payment/vnpay/vnp_return_url_path';
    private const XML_PATH_BANK_CODE = 'payment/vnpay/bank_code';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isActive(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    public function getTmnCode(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_TMN_CODE, ScopeInterface::SCOPE_STORE);
    }

    public function getHashSecret(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_HASH_SECRET, ScopeInterface::SCOPE_STORE);
    }

    public function getGatewayUrl(): string
    {
        $url = (string)$this->scopeConfig->getValue(self::XML_PATH_GATEWAY_URL, ScopeInterface::SCOPE_STORE);

        return $url !== '' ? $url : 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
    }

    public function getReturnUrlPath(): string
    {
        $path = (string)$this->scopeConfig->getValue(self::XML_PATH_RETURN_PATH, ScopeInterface::SCOPE_STORE);

        return $path !== '' ? $path : 'vnpay/payment/return';
    }

    public function getBankCode(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_BANK_CODE, ScopeInterface::SCOPE_STORE);
    }
}
