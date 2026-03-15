<?php
declare(strict_types=1);

namespace Vendor\Weather\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Vendor\Weather\Model\Config;
use Vendor\Weather\Model\WeatherService;

class Data implements HttpGetActionInterface
{
    /**
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
        private readonly JsonFactory $jsonFactory,
        private readonly WeatherService $weatherService,
        private readonly Config $config
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $cities = [];
        $pool = array_values(array_unique(array_filter(array_map(
            'trim',
            array_merge($this->config->getCities(), self::EXTRA_CITIES)
        ))));

        foreach (array_slice($pool, 0, 8) as $city) {
            $cities[] = $this->weatherService->getWeatherData($city);
        }

        return $result->setData([
            'success' => true,
            'cities' => $cities,
            'updated_at' => gmdate('c'),
        ]);
    }
}
