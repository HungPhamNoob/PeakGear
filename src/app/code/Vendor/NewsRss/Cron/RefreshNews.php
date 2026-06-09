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
        foreach (['travel', 'business'] as $feedCode) {
            try {
                $items = $this->newsService->getNews($feedCode);
                $this->logger->info(
                    sprintf('NewsRSS Cron: Refreshed %d %s news items.', count($items), $feedCode)
                );
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf('NewsRSS Cron error for %s: %s', $feedCode, $e->getMessage())
                );
            }
        }
    }
}
