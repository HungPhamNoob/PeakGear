<?php
declare(strict_types=1);

namespace Vendor\CurrencyRate\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Vendor\CurrencyRate\Model\CurrencyService;

class CurrencyRate extends Template
{
    private array $flagEmojis = [
        'USD' => '🇺🇸', 'EUR' => '🇪🇺', 'JPY' => '🇯🇵',
        'CNY' => '🇨🇳', 'GBP' => '🇬🇧', 'AUD' => '🇦🇺',
        'KRW' => '🇰🇷', 'SGD' => '🇸🇬',
    ];

    public function __construct(
        Context $context,
        private CurrencyService $currencyService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getAllRates(): array
    {
        return $this->currencyService->getAllRates();
    }

    public function formatRate(float $rate): string
    {
        return number_format($rate, 0, '.', ',');
    }

    public function getFlagEmoji(string $code): string
    {
        return $this->flagEmojis[$code] ?? '💱';
    }
}
