<?php
declare(strict_types=1);

namespace Vendor\Weather\Controller\Index;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Vendor\Weather\Model\WeatherService;

class Weekly implements HttpGetActionInterface
{
    private const GEOCODE_API = 'https://geocoding-api.open-meteo.com/v1/search';
    private const FORECAST_API = 'https://api.open-meteo.com/v1/forecast';
    private const CACHE_TTL = 600;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly WeatherService $weatherService,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $city = trim((string)$this->request->getParam('city', ''));

        if ($city === '') {
            return $result->setData([
                'success' => false,
                'message' => 'Missing city parameter.',
            ]);
        }

        $cacheKey = $this->getCacheKey($city);
        $cached = $this->loadCache($cacheKey);
        if ($cached !== null) {
            return $result->setData([
                'success' => true,
                'city' => $city,
                'source' => $cached['source'] ?? 'cache',
                'days' => $cached['days'],
                'cached' => true,
            ]);
        }

        $days = $this->fetchOpenMeteoForecast($city);
        $source = 'open-meteo';

        if ($days === []) {
            $days = $this->buildFallbackForecast($city);
            $source = 'fallback';
        }

        $this->saveCache($cacheKey, $days, $source);

        return $result->setData([
            'success' => true,
            'city' => $city,
            'source' => $source,
            'days' => $days,
        ]);
    }

    private function getCacheKey(string $city): string
    {
        $normalized = strtolower(trim($city));

        return 'vendor_weather_weekly_' . md5($normalized);
    }

    private function loadCache(string $key): ?array
    {
        $cached = $this->cache->load($key);
        if ($cached === false || $cached === '') {
            return null;
        }

        $decoded = json_decode($cached, true);
        if (!is_array($decoded) || !isset($decoded['days']) || !is_array($decoded['days'])) {
            return null;
        }

        return $decoded;
    }

    private function saveCache(string $key, array $days, string $source): void
    {
        if ($days === []) {
            return;
        }

        $payload = json_encode([
            'days' => $days,
            'source' => $source,
        ]);

        if ($payload === false) {
            return;
        }

        $this->cache->save($payload, $key, [], self::CACHE_TTL);
    }

    /**
     * @return array<int, array{date:string, avg:float, min:float, max:float, precipitation:float, label:string}>
     */
    private function fetchOpenMeteoForecast(string $city): array
    {
        try {
            $geo = $this->fetchJson(self::GEOCODE_API . '?' . http_build_query([
                'name' => $city,
                'count' => 1,
                'language' => 'vi',
                'format' => 'json',
            ]));
            if ($geo === null || empty($geo['results'][0])) {
                return [];
            }

            $first = $geo['results'][0];
            $lat = (float)($first['latitude'] ?? 0.0);
            $lon = (float)($first['longitude'] ?? 0.0);
            if ($lat === 0.0 && $lon === 0.0) {
                return [];
            }

            $forecast = $this->fetchJson(self::FORECAST_API . '?' . http_build_query([
                'latitude' => $lat,
                'longitude' => $lon,
                'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,weathercode',
                'forecast_days' => 10,
                'timezone' => 'Asia/Ho_Chi_Minh',
            ]));
            if ($forecast === null || !isset($forecast['daily']) || !is_array($forecast['daily'])) {
                return [];
            }

            $daily = $forecast['daily'];
            $times = $daily['time'] ?? [];
            $mins = $daily['temperature_2m_min'] ?? [];
            $maxs = $daily['temperature_2m_max'] ?? [];
            $precips = $daily['precipitation_sum'] ?? [];
            $codes = $daily['weathercode'] ?? [];

            $count = min(count($times), count($mins), count($maxs));
            if ($count < 1) {
                return [];
            }

            $days = [];
            for ($i = 0; $i < $count; $i++) {
                $min = round((float)$mins[$i], 1);
                $max = round((float)$maxs[$i], 1);
                $days[] = [
                    'date' => (string)$times[$i],
                    'avg' => round(($min + $max) / 2, 1),
                    'min' => $min,
                    'max' => $max,
                    'precipitation' => round((float)($precips[$i] ?? 0.0), 1),
                    'label' => $this->mapWeatherCode((int)($codes[$i] ?? 0)),
                ];
            }

            return $days;
        } catch (\Throwable $e) {
            $this->logger->warning('Weekly forecast fetch failed', ['city' => $city, 'exception' => $e]);

            return [];
        }
    }

    private function fetchJson(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "User-Agent: PeakGearWeather/1.0\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, array{date:string, avg:float, min:float, max:float, precipitation:float, label:string}>
     */
    private function buildFallbackForecast(string $city): array
    {
        $current = $this->weatherService->getWeatherData($city);
        $base = (float)($current['temperature'] ?? 24.0);
        $humidity = (int)($current['humidity'] ?? 65);
        $label = (string)($current['description'] ?? 'Có mây');

        $start = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));
        $days = [];
        for ($i = 0; $i < 10; $i++) {
            $delta = sin((float)$i / 2.0) * 2.4 + (($i % 3) - 1) * 0.4;
            $avg = round($base + $delta, 1);
            $min = round($avg - 2.2, 1);
            $max = round($avg + 2.2, 1);
            $precip = max(0.0, round(($humidity / 100.0) * 4.0 + (($i % 2 === 0) ? 0.4 : 1.2), 1));
            $date = $start->modify('+' . $i . ' day')->format('Y-m-d');

            $days[] = [
                'date' => $date,
                'avg' => $avg,
                'min' => $min,
                'max' => $max,
                'precipitation' => $precip,
                'label' => $label,
            ];
        }

        return $days;
    }

    private function mapWeatherCode(int $code): string
    {
        if ($code === 0) {
            return 'Trời quang';
        }
        if (in_array($code, [1, 2], true)) {
            return 'Có mây nhẹ';
        }
        if ($code === 3) {
            return 'Nhiều mây';
        }
        if (in_array($code, [45, 48], true)) {
            return 'Sương mù';
        }
        if (in_array($code, [51, 53, 55, 56, 57], true)) {
            return 'Mưa phùn';
        }
        if (in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true)) {
            return 'Mưa';
        }
        if (in_array($code, [71, 73, 75, 77, 85, 86], true)) {
            return 'Tuyết';
        }
        if (in_array($code, [95, 96, 99], true)) {
            return 'Dông';
        }

        return 'Thời tiết thay đổi';
    }
}
