<?php
declare(strict_types=1);

namespace Vendor\NewsRss\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Vendor\NewsRss\Model\NewsService;

class News extends Template
{
    public function __construct(
        Context $context,
        private NewsService $newsService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getNewsItems(int $limit = 0): array
    {
        $items = $this->newsService->getNews();
        if ($limit > 0) {
            return array_slice($items, 0, $limit);
        }

        return $items;
    }

    public function formatPubDate(string $dateStr): string
    {
        if (empty($dateStr)) {
            return date('d/m/Y');
        }
        try {
            $ts = strtotime($dateStr);
            return $ts ? date('d/m/Y H:i', $ts) : $dateStr;
        } catch (\Throwable $e) {
            return $dateStr;
        }
    }

    public function truncateText(string $text, int $length = 150): string
    {
        $text = strip_tags($text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . '...';
    }

    public function getPublishedTimestamp(array $item): int
    {
        $value = (string)($item['pub_date'] ?? '');
        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);

        return $timestamp ?: 0;
    }
}
