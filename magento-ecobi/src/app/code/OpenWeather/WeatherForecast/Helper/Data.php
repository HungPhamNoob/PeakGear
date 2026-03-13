<?php
namespace OpenWeather\WeatherForecast\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Magento\Framework\Exception\LocalizedException;

class Data extends AbstractHelper
{
  protected $scopeConfig;

  public function __construct(
    ScopeConfigInterface $scopeConfig
  ) {
    $this->scopeConfig = $scopeConfig;
  }

  /**
   * @param string|null $city
   * @return array
   * @throws LocalizedException
   */
  public function getWeatherData($city = null)
  {
    try {
      $apiKey = $this->scopeConfig->getValue('weather_settings/general/api_key', ScopeInterface::SCOPE_STORE);
      $city = $city ?? $this->scopeConfig->getValue('weather_settings/general/city', ScopeInterface::SCOPE_STORE);

      if (empty($city)) {
        throw new LocalizedException(__('City parameter is required'));
      }

      if (empty($apiKey)) {
        throw new LocalizedException(__('API key is not configured'));
      }

      $client = new Client();

      // Get current weather
      $currentResponse = $client->get("http://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$apiKey}&units=metric&lang=en");
      $currentData = json_decode($currentResponse->getBody()->getContents(), true);

      if (isset($currentData['cod']) && $currentData['cod'] === '404') {
        throw new LocalizedException(__('City "%1" not found', $city));
      }

      // Get 5-day forecast
      $forecastResponse = $client->get("http://api.openweathermap.org/data/2.5/forecast?q={$city}&appid={$apiKey}&units=metric&lang=en");
      $forecastData = json_decode($forecastResponse->getBody()->getContents(), true);

      return [
        'success' => true,
        'data' => [
          'current' => $currentData,
          'forecast' => $forecastData,
          'icon' => $currentData['weather'][0]['icon']
        ]
      ];

    } catch (RequestException $e) {
      return [
        'success' => false,
        'message' => __('Failed to fetch weather data: %1', $e->getMessage())
      ];
    } catch (LocalizedException $e) {
      return [
        'success' => false,
        'message' => $e->getMessage()
      ];
    } catch (\Exception $e) {
      return [
        'success' => false,
        'message' => __('An unexpected error occurred while fetching weather data')
      ];
    }
  }
}