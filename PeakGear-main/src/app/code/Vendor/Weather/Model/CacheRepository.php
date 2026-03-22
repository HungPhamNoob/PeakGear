<?php
declare(strict_types=1);

namespace Vendor\Weather\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Reads and writes cached weather rows keyed by city.
 */
class CacheRepository
{
    private const TABLE_NAME = 'vendor_weather_cache';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFreshCityWeather(string $cityKey, int $ttl): ?array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()->select()
                ->from($this->getTableName())
                ->where('city_key = ?', $cityKey)
                ->where('cached_at >= ?', $this->dateTime->gmtDate('Y-m-d H:i:s', time() - $ttl))
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'city_name' => (string)($row['city_name'] ?? ''),
            'temperature' => (float)($row['temperature'] ?? 0.0),
            'feels_like' => (float)($row['feels_like'] ?? 0.0),
            'humidity' => (int)($row['humidity'] ?? 0),
            'description' => (string)($row['description'] ?? ''),
            'icon_code' => (string)($row['icon_code'] ?? '01d'),
            'wind_speed' => (float)($row['wind_speed'] ?? 0.0),
        ];
    }

    /**
     * @param array{city_name:string, temperature:float, feels_like:float, humidity:int, description:string, icon_code:string, wind_speed:float} $weather
     */
    public function save(string $cityKey, array $weather): void
    {
        $this->getConnection()->insertOnDuplicate(
            $this->getTableName(),
            [
                'city_key' => $cityKey,
                'city_name' => $weather['city_name'],
                'temperature' => $weather['temperature'],
                'feels_like' => $weather['feels_like'],
                'humidity' => $weather['humidity'],
                'description' => $weather['description'],
                'icon_code' => $weather['icon_code'],
                'wind_speed' => $weather['wind_speed'],
                'cached_at' => $this->dateTime->gmtDate('Y-m-d H:i:s'),
            ],
            ['city_name', 'temperature', 'feels_like', 'humidity', 'description', 'icon_code', 'wind_speed', 'cached_at']
        );
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
