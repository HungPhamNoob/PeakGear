<?php
declare(strict_types=1);

namespace Vendor\Weather\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Vendor\Weather\Api\WeatherProviderInterface;
use Vendor\Weather\Model\Config;

/**
 * Integrates with OpenWeather's current weather endpoint.
 * Falls back to wttr.in (no API key required) when no OWM key is configured.
 */
class OpenWeatherProvider implements WeatherProviderInterface
{
    private const API_URL  = 'https://api.openweathermap.org/data/2.5/weather';
    private const WTTR_URL = 'https://wttr.in/%s?format=j1';

    public function __construct(
        private readonly Curl   $curl,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function fetch(string $city): array
    {
        if (!$this->config->hasConfiguredApiKey()) {
            return $this->fetchFromWttr($city);
        }

        $query = http_build_query([
            'q'     => $city,
            'appid' => $this->config->getApiKey(),
            'units' => $this->config->getUnits(),
            'lang'  => 'vi',
        ]);

        $this->curl->setTimeout(8);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
        $this->curl->get(self::API_URL . '?' . $query);

        if ($this->curl->getStatus() !== 200) {
            return $this->fetchFromWttr($city);
        }

        $payload = json_decode($this->curl->getBody(), true);
        if (!is_array($payload) || !isset($payload['main'], $payload['weather'][0])) {
            return $this->fetchFromWttr($city);
        }

        return [
            'city_name'   => (string)($payload['name'] ?? $city),
            'temperature' => round((float)$payload['main']['temp'], 1),
            'feels_like'  => round((float)$payload['main']['feels_like'], 1),
            'humidity'    => (int)$payload['main']['humidity'],
            'description' => ucfirst((string)($payload['weather'][0]['description'] ?? '')),
            'icon_code'   => (string)($payload['weather'][0]['icon'] ?? '01d'),
            'wind_speed'  => round((float)($payload['wind']['speed'] ?? 0.0), 1),
        ];
    }

    /**
     * Fetch current weather from wttr.in — free, no API key required.
     * Returns the same array shape as the OWM fetch.
     */
    private function fetchFromWttr(string $city): array
    {
        $normalizedCity = $this->normalizeCity($city);
        $url = sprintf(self::WTTR_URL, rawurlencode($normalizedCity));

        $this->curl->setTimeout(10);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 6);
        $this->curl->addHeader('User-Agent', 'PeakGear/1.0 (weather-widget)');
        $this->curl->get($url);

        if ($this->curl->getStatus() !== 200) {
            throw new LocalizedException(__('wttr.in could not fetch weather for %1.', $city));
        }

        $payload = json_decode($this->curl->getBody(), true);
        if (!is_array($payload) || empty($payload['current_condition'][0])) {
            throw new LocalizedException(__('wttr.in returned invalid data for %1.', $city));
        }

        $cc   = $payload['current_condition'][0];
        $desc = (string)($cc['weatherDesc'][0]['value'] ?? '');

        return [
            'city_name'   => $city,
            'temperature' => round((float)($cc['temp_C'] ?? 0), 1),
            'feels_like'  => round((float)($cc['FeelsLikeC'] ?? 0), 1),
            'humidity'    => (int)($cc['humidity'] ?? 0),
            'description' => $this->translateDescription($desc),
            'icon_code'   => $this->mapWttrCode((int)($cc['weatherCode'] ?? 800)),
            'wind_speed'  => round((float)($cc['windspeedKmph'] ?? 0) / 3.6, 1),
        ];
    }

    /**
     * Normalize Vietnamese city names to wttr.in-compatible search strings.
     * Without this, "Ha Noi" can resolve to wrong locations.
     */
    private function normalizeCity(string $city): string
    {
        $map = [
            'Ha Noi'           => 'Hanoi,Vietnam',
            'Ho Chi Minh City' => 'Ho Chi Minh City,Vietnam',
            'Da Nang'          => 'Danang,Vietnam',
            'Da Lat'           => 'Dalat,Vietnam',
            'Sapa'             => 'Sapa,Vietnam',
            'Ha Giang'         => 'Ha Giang,Vietnam',
            'Phu Quoc'         => 'Phu Quoc,Vietnam',
            'Hue'              => 'Hue,Vietnam',
            'Nha Trang'        => 'Nha Trang,Vietnam',
            'Quy Nhon'         => 'Quy Nhon,Vietnam',
            'Can Tho'          => 'Can Tho,Vietnam',
            'Vung Tau'         => 'Vung Tau,Vietnam',
        ];

        return $map[$city] ?? $city;
    }

    /**
     * Translate an English weather description to Vietnamese.
     */
    private function translateDescription(string $description): string
    {
        $map = [
            'sunny'                                          => 'Trời nắng',
            'clear'                                          => 'Trời quang',
            'partly cloudy'                                  => 'Có mây rải rác',
            'cloudy'                                         => 'Nhiều mây',
            'overcast'                                       => 'Trời u ám',
            'mist'                                           => 'Sương mù nhẹ',
            'fog'                                            => 'Sương mù',
            'freezing fog'                                   => 'Sương giá',
            'patchy rain nearby'                             => 'Có thể có mưa',
            'patchy rain possible'                           => 'Có khả năng mưa',
            'patchy snow possible'                           => 'Có khả năng tuyết rơi',
            'patchy sleet possible'                          => 'Có khả năng mưa đá nhẹ',
            'thundery outbreaks nearby'                      => 'Có sấm sét gần đây',
            'thundery outbreaks possible'                    => 'Có khả năng giông sét',
            'blowing snow'                                   => 'Tuyết bay',
            'blizzard'                                       => 'Bão tuyết',
            'light drizzle'                                  => 'Mưa phùn nhẹ',
            'freezing drizzle'                               => 'Mưa phùn giá lạnh',
            'heavy freezing drizzle'                         => 'Mưa phùn giá lạnh nặng hạt',
            'light rain'                                     => 'Mưa nhẹ',
            'moderate rain'                                  => 'Mưa vừa',
            'heavy rain'                                     => 'Mưa to',
            'light rain shower'                              => 'Mưa rào nhẹ',
            'moderate or heavy rain shower'                  => 'Mưa rào vừa đến to',
            'torrential rain shower'                         => 'Mưa như trút nước',
            'light sleet'                                    => 'Mưa đá nhẹ',
            'moderate or heavy sleet'                        => 'Mưa đá vừa đến nặng',
            'light snow'                                     => 'Tuyết nhẹ',
            'moderate snow'                                  => 'Tuyết vừa',
            'patchy heavy snow'                              => 'Tuyết to rải rác',
            'heavy snow'                                     => 'Tuyết dày',
            'ice pellets'                                    => 'Hạt băng',
            'light showers of ice pellets'                   => 'Mưa hạt băng nhẹ',
            'moderate or heavy showers of ice pellets'       => 'Mưa hạt băng vừa đến nặng',
            'light sleet showers'                            => 'Mưa đá rào nhẹ',
            'moderate or heavy sleet showers'                => 'Mưa đá rào vừa đến nặng',
            'light snow showers'                             => 'Mưa tuyết nhẹ',
            'moderate or heavy snow showers'                 => 'Mưa tuyết vừa đến nặng',
            'patchy light rain in area with thunder'         => 'Mưa nhẹ có giông sét',
            'moderate or heavy rain in area with thunder'    => 'Mưa vừa đến to có giông sét',
            'patchy light snow in area with thunder'         => 'Tuyết nhẹ có giông sét',
            'moderate or heavy snow in area with thunder'    => 'Tuyết nặng có giông sét',
        ];

        $lower = mb_strtolower(trim($description));

        return $map[$lower] ?? ucfirst($lower);
    }

    /**
     * Map wttr.in numeric weather code → OpenWeatherMap-compatible icon code.
     * OWM icon format: {code}d  e.g. "01d", "10d"
     */
    private function mapWttrCode(int $code): string
    {
        return match (true) {
            $code === 113                                                                              => '01d', // Clear/Sunny
            $code === 116                                                                              => '02d', // Partly cloudy
            $code === 119                                                                              => '03d', // Cloudy
            $code === 122                                                                              => '04d', // Overcast
            in_array($code, [143, 248, 260], true)                                                    => '50d', // Mist/Fog
            in_array($code, [176, 263, 266, 293, 296, 299, 302, 305, 308], true)                      => '10d', // Rain
            in_array($code, [353, 356, 359, 362, 365], true)                                          => '09d', // Showers
            in_array($code, [200, 386, 389, 392, 395], true)                                          => '11d', // Thunderstorm
            in_array($code, [227, 230, 281, 284, 311, 314, 317, 320, 323, 326, 329, 332, 335, 338, 368, 371, 374], true) => '13d', // Snow/Sleet
            default                                                                                   => '03d', // Default: cloudy
        };
    }
}
