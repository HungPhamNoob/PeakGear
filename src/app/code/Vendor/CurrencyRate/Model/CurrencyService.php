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
