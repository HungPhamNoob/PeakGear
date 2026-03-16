<?php
declare(strict_types=1);

namespace Vendor\Weather\Cron;

use Vendor\Weather\Model\WeatherService;
use Psr\Log\LoggerInterface;

class RefreshWeather
{
    public function __construct(
        private WeatherService  $weatherService,
        private LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        $this->logger->info('Weather Cron: Starting refresh...');
        try {
            $data = $this->weatherService->getAllCities();
            $this->logger->info('Weather Cron: Refreshed ' . count($data) . ' cities.');
        } catch (\Exception $e) {
            $this->logger->error('Weather Cron error: ' . $e->getMessage());
        }
    }
}
