<?php
declare(strict_types=1);

namespace Vendor\CurrencyRate\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Persists normalized currency rates in a small cache table.
 */
class CacheRepository
{
    private const TABLE_NAME = 'vendor_currency_rate_cache';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @return array<string, array{name:string, buy_transfer:float, buy_cash:float, sell:float}>
     */
    public function getFreshRates(int $ttl): array
    {
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()->select()
                ->from($this->getTableName())
                ->where('cached_at >= ?', $this->dateTime->gmtDate('Y-m-d H:i:s', time() - $ttl))
                ->order('currency_code ASC')
        );

        $rates = [];
        foreach ($rows as $row) {
            $rates[$row['currency_code']] = [
                'name' => (string)$row['currency_name'],
                'buy_transfer' => (float)$row['buy_transfer'],
                'buy_cash' => (float)$row['buy_cash'],
                'sell' => (float)$row['sell'],
            ];
        }

        return $rates;
    }

    /**
     * @param array<string, array{name:string, buy_transfer:float, buy_cash:float, sell:float}> $rates
     */
    public function save(array $rates): void
    {
        $connection = $this->getConnection();
        $table = $this->getTableName();
        $timestamp = $this->dateTime->gmtDate('Y-m-d H:i:s');

        $connection->delete($table);
        foreach ($rates as $currencyCode => $rate) {
            $connection->insert($table, [
                'currency_code' => $currencyCode,
                'currency_name' => $rate['name'],
                'buy_transfer' => $rate['buy_transfer'],
                'buy_cash' => $rate['buy_cash'],
                'sell' => $rate['sell'],
                'cached_at' => $timestamp,
            ]);
        }
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function getTableName(): string
    {
        return $this->resourceConnection->getTableName(self::TABLE_NAME);
    }
}
