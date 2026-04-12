<?php
declare(strict_types=1);

namespace Vendor\CurrencyRate\Model;

use Vendor\CurrencyRate\Api\RateProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Coordinates cache lookup, remote fetch, and fallback data for storefront consumers.
 */
class CurrencyService
{
    // Fallback demo rates (VND per 1 foreign currency)
    private array $demoRates = [
        'USD' => ['name' => 'Đô la Mỹ',      'buy_transfer' => 25230.0, 'buy_cash' => 25200.0, 'sell' => 25530.0],
        'EUR' => ['name' => 'Euro',             'buy_transfer' => 27520.0, 'buy_cash' => 27480.0, 'sell' => 28150.0],
        'JPY' => ['name' => 'Yên Nhật',         'buy_transfer' => 164.8,   'buy_cash' => 163.5,   'sell' => 172.2],
        'CNY' => ['name' => 'Nhân dân tệ TQ',   'buy_transfer' => 3426.0,  'buy_cash' => 3390.0,  'sell' => 3542.0],
        'GBP' => ['name' => 'Bảng Anh',          'buy_transfer' => 31950.0, 'buy_cash' => 31900.0, 'sell' => 33100.0],
        'AUD' => ['name' => 'Đô la Úc',          'buy_transfer' => 15820.0, 'buy_cash' => 15780.0, 'sell' => 16310.0],
        'KRW' => ['name' => 'Won Hàn Quốc',       'buy_transfer' => 17.12,   'buy_cash' => 17.0,    'sell' => 18.42],
        'SGD' => ['name' => 'Đô la Singapore',    'buy_transfer' => 18560.0, 'buy_cash' => 18500.0, 'sell' => 19110.0],
        'CAD' => ['name' => 'Đô la Canada',       'buy_transfer' => 18650.0, 'buy_cash' => 18600.0, 'sell' => 19350.0],
        'CHF' => ['name' => 'Franc Thụy Sĩ',      'buy_transfer' => 28750.0, 'buy_cash' => 28700.0, 'sell' => 29680.0],
        'HKD' => ['name' => 'Đô la Hồng Kông',    'buy_transfer' => 3190.0,  'buy_cash' => 3160.0,  'sell' => 3320.0],
        'INR' => ['name' => 'Rupee Ấn Độ',        'buy_transfer' => 298.0,   'buy_cash' => 290.0,   'sell' => 315.0],
        'KWD' => ['name' => 'Dinar Kuwait',       'buy_transfer' => 82000.0, 'buy_cash' => 81700.0, 'sell' => 84600.0],
        'MYR' => ['name' => 'Ringgit Malaysia',   'buy_transfer' => 5600.0,  'buy_cash' => 5520.0,  'sell' => 5790.0],
        'NOK' => ['name' => 'Krone Na Uy',        'buy_transfer' => 2420.0,  'buy_cash' => 2400.0,  'sell' => 2550.0],
        'RUB' => ['name' => 'Rúp Nga',            'buy_transfer' => 270.0,   'buy_cash' => 260.0,   'sell' => 295.0],
        'SAR' => ['name' => 'Riyal Ả Rập Xê Út',  'buy_transfer' => 6680.0,  'buy_cash' => 6620.0,  'sell' => 6900.0],
        'SEK' => ['name' => 'Krona Thụy Điển',    'buy_transfer' => 2380.0,  'buy_cash' => 2350.0,  'sell' => 2520.0],
        'THB' => ['name' => 'Baht Thái',          'buy_transfer' => 720.0,   'buy_cash' => 700.0,   'sell' => 760.0],
    ];

    public function __construct(
        private readonly Config $config,
        private readonly CacheRepository $cacheRepository,
        private readonly RateProviderInterface $rateProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get all currency rates with caching.
     *
     * @return array<string, array{name:string, buy_transfer:float, buy_cash:float, sell:float}>
     */
    public function getAllRates(): array
    {
        if (!$this->config->isEnabled()) {
            return [];
        }

        $ttl = $this->config->getCacheTtl();
        $cachedRates = $this->cacheRepository->getFreshRates($ttl);

        if ($cachedRates !== []) {
            return $cachedRates;
        }

        try {
            $rates = $this->rateProvider->fetchRates();
            $this->cacheRepository->save($rates);
        } catch (\Exception $e) {
            $this->logger->warning(
                'CurrencyRate remote refresh failed; serving fallback rates.',
                ['exception' => $e]
            );
            $rates = $this->demoRates;
        }

        return $rates;
    }
}
