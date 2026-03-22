<?php
declare(strict_types=1);

namespace Vendor\Weather\Api;

/**
 * Fetches normalized weather data for a requested city.
 */
interface WeatherProviderInterface
{
    /**
     * @return array{city_name:string, temperature:float, feels_like:float, humidity:int, description:string, icon_code:string, wind_speed:float}
     */
    public function fetch(string $city): array;
}
