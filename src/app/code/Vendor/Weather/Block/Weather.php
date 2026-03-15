<?php
declare(strict_types=1);

namespace Vendor\Weather\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Vendor\Weather\Model\Config;
use Vendor\Weather\Model\WeatherService;

class Weather extends Template
{
    /**
     * Supplemental city pool for richer weather cards.
     *
     * @var string[]
     */
    private const EXTRA_CITIES = [
        'Da Nang',
        'Nha Trang',
        'Ha Giang',
        'Phu Quoc',
        'Hue',
        'Quy Nhon',
        'Can Tho',
        'Vung Tau',
    ];

    public function __construct(
        Context $context,
        private readonly WeatherService $weatherService,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getAllCitiesWeather(): array
    {
        return $this->weatherService->getAllCities();
    }

    public function getWeatherForCity(string $city): array
    {
        return $this->weatherService->getWeatherData($city);
    }

    /**
     * @return array<int, array{city_name:string, temperature:float, feels_like:float, humidity:int, description:string, icon_code:string, wind_speed:float}>
     */
    public function getHeroCitiesWeather(int $limit = 6): array
    {
        return $this->getCuratedCitiesWeather($limit);
    }

    /**
     * @return array<int, array{city_name:string, temperature:float, feels_like:float, humidity:int, description:string, icon_code:string, wind_speed:float}>
     */
    public function getNewsCitiesWeather(int $limit = 12): array
    {
        return $this->getCuratedCitiesWeather($limit);
    }

    public function getIconUrl(string $iconCode): string
    {
        return 'https://openweathermap.org/img/wn/' . $iconCode . '@2x.png';
    }

    public function getWindSpeedLabel(float $speed): string
    {
        if ($speed < 1.5) return 'Lặng gió';
        if ($speed < 5.5) return 'Gió nhẹ';
        if ($speed < 11)  return 'Gió vừa';
        if ($speed < 20)  return 'Gió mạnh';
        return 'Bão';
    }

    public function getTrekkingAdvice(float $temp, int $humidity): string
    {
        if ($temp < 10) return '🧥 Rất lạnh - Mặc đủ ấm, áo lông vũ bắt buộc';
        if ($temp < 18) return '🧤 Lạnh - Áo khoác và găng tay cần thiết';
        if ($temp < 25) return '✅ Lý tưởng cho trekking';
        if ($temp < 32) return '🌡️ Ấm - Mang đủ nước uống';
        return '🔥 Rất nóng - Hạn chế hoạt động ban ngày';
    }

    /**
     * @return array<int, array{city_name:string, temperature:float, feels_like:float, humidity:int, description:string, icon_code:string, wind_speed:float}>
     */
    private function getCuratedCitiesWeather(int $limit): array
    {
        $configured = $this->config->getCities();
        $cityPool = array_values(array_unique(array_filter(array_map('trim', array_merge($configured, self::EXTRA_CITIES)))));

        if ($limit > 0) {
            $cityPool = array_slice($cityPool, 0, $limit);
        }

        $result = [];
        foreach ($cityPool as $city) {
            $result[] = $this->weatherService->getWeatherData($city);
        }

        return $result;
    }
}
