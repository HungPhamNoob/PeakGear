<?php

declare(strict_types=1);

namespace PeakGear\ContactWidget\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'peakgear_contact_widget/general/enabled';
    private const XML_PATH_PHONE_ENABLED = 'peakgear_contact_widget/general/phone_enabled';
    private const XML_PATH_PHONE_NUMBER = 'peakgear_contact_widget/general/phone_number';
    private const XML_PATH_ZALO_ENABLED = 'peakgear_contact_widget/general/zalo_enabled';
    private const XML_PATH_ZALO_URL = 'peakgear_contact_widget/general/zalo_url';
    private const XML_PATH_CONTACT_ENABLED = 'peakgear_contact_widget/general/contact_enabled';
    private const XML_PATH_COLLAPSED = 'peakgear_contact_widget/general/collapsed_by_default';
    private const XML_PATH_SHOW_ON_MOBILE = 'peakgear_contact_widget/general/show_on_mobile';
    private const XML_PATH_SHOW_ON_DESKTOP = 'peakgear_contact_widget/general/show_on_desktop';
    private const XML_PATH_BOTTOM_OFFSET = 'peakgear_contact_widget/general/bottom_offset';
    private const XML_PATH_RIGHT_OFFSET = 'peakgear_contact_widget/general/right_offset';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_ENABLED, $storeId);
    }

    public function isPhoneEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_PHONE_ENABLED, $storeId);
    }

    public function getPhoneNumber(?int $storeId = null): string
    {
        return trim((string) $this->getValue(self::XML_PATH_PHONE_NUMBER, $storeId));
    }

    public function isZaloEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_ZALO_ENABLED, $storeId);
    }

    public function getZaloUrl(?int $storeId = null): string
    {
        return trim((string) $this->getValue(self::XML_PATH_ZALO_URL, $storeId));
    }

    public function isContactEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_CONTACT_ENABLED, $storeId);
    }

    public function isCollapsedByDefault(?int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_COLLAPSED, $storeId);
    }

    public function showOnMobile(?int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_SHOW_ON_MOBILE, $storeId);
    }

    public function showOnDesktop(?int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_SHOW_ON_DESKTOP, $storeId);
    }

    public function getBottomOffset(?int $storeId = null): int
    {
        return $this->normalizeOffset((string) $this->getValue(self::XML_PATH_BOTTOM_OFFSET, $storeId), 24);
    }

    public function getRightOffset(?int $storeId = null): int
    {
        return $this->normalizeOffset((string) $this->getValue(self::XML_PATH_RIGHT_OFFSET, $storeId), 16);
    }

    private function getValue(string $path, ?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function isSetFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function normalizeOffset(string $value, int $default): int
    {
        $offset = (int) trim($value);
        if ($offset < 0) {
            return $default;
        }

        return min($offset, 240);
    }
}
