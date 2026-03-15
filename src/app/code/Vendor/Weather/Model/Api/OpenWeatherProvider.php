<?php
declare(strict_types=1);

namespace Vendor\Weather\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Vendor\Weather\Api\WeatherProviderInterface;
use Vendor\Weather\Model\Config;

/**
 * Integrates with OpenWeather's current weather endpoint.
 */
class OpenWeatherProvider implements WeatherProviderInterface
{
    private const API_URL = 'https://api.openweathermap.org/data/2.5/weather';

    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function fetch(string $city): array
    {
        if (!$this->config->hasConfiguredApiKey()) {
            throw new LocalizedException(__('Missing OpenWeather API key.'));
        }

        $query = http_build_query([
            'q' => $city,
            'appid' => $this->config->getApiKey(),
            'units' => $this->config->getUnits(),
            'lang' => 'vi',
        ]);

        $this->curl->setTimeout(8);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
        $this->curl->get(self::API_URL . '?' . $query);

        if ($this->curl->getStatus() !== 200) {
            throw new LocalizedException(__('Unable to fetch weather data for %1.', $city));
        }

        $payload = json_decode($this->curl->getBody(), true);
        if (!is_array($payload) || !isset($payload['main'], $payload['weather'][0])) {
            throw new LocalizedException(__('Weather provider returned an invalid payload for %1.', $city));
        }

        return [
            'city_name' => (string)($payload['name'] ?? $city),
            'temperature' => round((float)$payload['main']['temp'], 1),
            'feels_like' => round((float)$payload['main']['feels_like'], 1),
            'humidity' => (int)$payload['main']['humidity'],
            'description' => ucfirst((string)($payload['weather'][0]['description'] ?? '')),
            'icon_code' => (string)($payload['weather'][0]['icon'] ?? '01d'),
            'wind_speed' => round((float)($payload['wind']['speed'] ?? 0.0), 1),
        ];
    }
}
