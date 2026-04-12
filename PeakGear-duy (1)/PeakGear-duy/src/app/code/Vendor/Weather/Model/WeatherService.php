<?php
declare(strict_types=1);

namespace Vendor\Weather\Model;

use Vendor\Weather\Api\WeatherProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates cached weather retrieval with a remote provider and local fallbacks.
 */
class WeatherService
{
    // Fallback demo data for offline/demo environments
    private array $demoData = [
        'Sapa'              => ['temp' => 15.2, 'feels_like' => 13.8, 'humidity' => 82, 'description' => 'Mây mù & sương, rất lạnh', 'icon' => '50d', 'wind_speed' => 3.5],
        'Da Lat'            => ['temp' => 19.5, 'feels_like' => 18.1, 'humidity' => 75, 'description' => 'Trời mát mẻ, có mây nhẹ', 'icon' => '03d', 'wind_speed' => 2.1],
        'Ha Noi'            => ['temp' => 28.3, 'feels_like' => 31.2, 'humidity' => 78, 'description' => 'Nắng có mây, nóng ẩm', 'icon' => '02d', 'wind_speed' => 4.2],
        'Ho Chi Minh City'  => ['temp' => 33.1, 'feels_like' => 38.5, 'humidity' => 68, 'description' => 'Nắng nóng, nhiều mây', 'icon' => '01d', 'wind_speed' => 5.8],
        'Mu Cang Chai'      => ['temp' => 12.4, 'feels_like' => 10.5, 'humidity' => 88, 'description' => 'Sương mù dày đặc, rất lạnh', 'icon' => '50d', 'wind_speed' => 1.9],
        'Fansipan'          => ['temp' => 5.8,  'feels_like' => 2.1,  'humidity' => 95, 'description' => 'Lạnh cực điểm, có mây mù', 'icon' => '50n', 'wind_speed' => 8.3],
        'Da Nang'           => ['temp' => 31.4, 'feels_like' => 35.6, 'humidity' => 70, 'description' => 'Nắng gián đoạn, có mây', 'icon' => '02d', 'wind_speed' => 4.8],
        'Nha Trang'         => ['temp' => 30.6, 'feels_like' => 34.4, 'humidity' => 73, 'description' => 'Trời quang, nắng nhẹ', 'icon' => '01d', 'wind_speed' => 5.2],
        'Ha Giang'          => ['temp' => 17.7, 'feels_like' => 16.2, 'humidity' => 84, 'description' => 'Có mây, trời mát', 'icon' => '03d', 'wind_speed' => 2.7],
        'Phu Quoc'          => ['temp' => 29.8, 'feels_like' => 33.2, 'humidity' => 80, 'description' => 'Có thể có mưa rào nhẹ', 'icon' => '10d', 'wind_speed' => 4.1],
        'Hue'               => ['temp' => 30.1, 'feels_like' => 34.0, 'humidity' => 74, 'description' => 'Nắng nóng, độ ẩm cao', 'icon' => '01d', 'wind_speed' => 3.9],
        'Quy Nhon'          => ['temp' => 29.3, 'feels_like' => 32.5, 'humidity' => 76, 'description' => 'Trời nắng, có gió biển', 'icon' => '02d', 'wind_speed' => 5.5],
        'Can Tho'           => ['temp' => 31.7, 'feels_like' => 36.1, 'humidity' => 72, 'description' => 'Nắng gắt buổi trưa', 'icon' => '01d', 'wind_speed' => 3.4],
        'Vung Tau'          => ['temp' => 29.4, 'feels_like' => 32.7, 'humidity' => 79, 'description' => 'Nhiều mây, gió nhẹ', 'icon' => '03d', 'wind_speed' => 6.1],
    ];

    public function __construct(
        private readonly Config $config,
        private readonly CacheRepository $cacheRepository,
        private readonly WeatherProviderInterface $weatherProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get weather data for a city, with DB caching.
     *
     * @return array{city_name:string, temperature:float, feels_like:float, humidity:int, description:string, icon_code:string, wind_speed:float}
     */
    public function getWeatherData(string $city): array
    {
        if (!$this->config->isEnabled()) {
            return $this->getDemoData($city);
        }

        $cached = $this->cacheRepository->getFreshCityWeather($city, $this->config->getCacheTtl());
        if ($cached !== null) {
            return $cached;
        }

        try {
            $data = $this->weatherProvider->fetch($city);
            $this->cacheRepository->save($city, $data);
        } catch (\Exception $e) {
            $this->logger->warning(
                'Weather provider refresh failed; using fallback data.',
                ['city' => $city, 'exception' => $e]
            );
            $data = $this->getDemoData($city);
        }

        return $data;
    }

    /**
     * Get weather for all configured cities.
     */
    public function getAllCities(): array
    {
        $result = [];
        foreach ($this->config->getCities() as $city) {
            $result[$city] = $this->getWeatherData($city);
        }

        return $result;
    }

    private function getDemoData(string $city): array
    {
        $d = $this->demoData[$city] ?? reset($this->demoData);
        return [
            'city_name'   => $city,
            'temperature' => $d['temp'],
            'feels_like'  => $d['feels_like'],
            'humidity'    => $d['humidity'],
            'description' => $d['description'],
            'icon_code'   => $d['icon'],
            'wind_speed'  => $d['wind_speed'],
        ];
    }
}
