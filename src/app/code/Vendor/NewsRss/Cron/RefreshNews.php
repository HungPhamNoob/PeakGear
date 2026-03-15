<?php
declare(strict_types=1);

namespace Vendor\NewsRss\Cron;

use Vendor\NewsRss\Model\NewsService;
use Psr\Log\LoggerInterface;

class RefreshNews
{
    public function __construct(
        private NewsService     $newsService,
        private LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        $this->logger->info('NewsRSS Cron: Starting refresh...');
        try {
            $items = $this->newsService->getNews();
            $this->logger->info('NewsRSS Cron: Refreshed ' . count($items) . ' news items.');
        } catch (\Exception $e) {
            $this->logger->error('NewsRSS Cron error: ' . $e->getMessage());
        }
    }
}
