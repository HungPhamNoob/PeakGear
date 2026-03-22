<?php
declare(strict_types=1);

namespace Vendor\CurrencyRate\Cron;

use Vendor\CurrencyRate\Model\CurrencyService;
use Psr\Log\LoggerInterface;

class RefreshCurrencyRate
{
    public function __construct(
        private CurrencyService $currencyService,
        private LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        $this->logger->info('CurrencyRate Cron: Starting refresh...');
        try {
            $rates = $this->currencyService->getAllRates();
            $this->logger->info('CurrencyRate Cron: Refreshed ' . count($rates) . ' currencies.');
        } catch (\Exception $e) {
            $this->logger->error('CurrencyRate Cron error: ' . $e->getMessage());
        }
    }
}
