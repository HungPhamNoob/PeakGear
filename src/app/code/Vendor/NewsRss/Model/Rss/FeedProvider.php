<?php
declare(strict_types=1);

namespace Vendor\NewsRss\Model\Rss;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Vendor\NewsRss\Api\NewsFeedProviderInterface;
use Vendor\NewsRss\Model\Config;

/**
 * Fetches and normalizes RSS content with Magento's HTTP client.
 */
class FeedProvider implements NewsFeedProviderInterface
{
    private const DEFAULT_ORIGIN = 'https://vnexpress.net';

    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function fetch(int $maxItems): array
    {
        $url = $this->config->getRssUrl();

        $this->curl->setTimeout(10);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
        $this->curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->curl->addHeader('Accept', 'application/rss+xml, application/xml, text/html;q=0.9, */*;q=0.8');
        $this->curl->addHeader('User-Agent', 'PeakGear NewsRss/1.0');
        $this->curl->get($url);

        if ($this->curl->getStatus() !== 200 || $this->curl->getBody() === '') {
            throw new LocalizedException(__('Unable to fetch RSS feed.'));
        }

        $body = $this->curl->getBody();
        if ($this->looksLikeXmlFeed($body)) {
            return $this->parseRssPayload($body, $maxItems);
        }

        return $this->parseVnExpressListing($body, $maxItems);
    }

    private function looksLikeXmlFeed(string $payload): bool
    {
        $head = ltrim($payload);

        return str_starts_with($head, '<?xml') || str_contains($head, '<rss');
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseRssPayload(string $payload, int $maxItems): array
    {
        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($payload);
        if (!$rss instanceof \SimpleXMLElement || !isset($rss->channel)) {
            throw new LocalizedException(__('RSS feed returned invalid XML.'));
        }

        $items = [];
        foreach ($rss->channel->item as $item) {
            if (count($items) >= $maxItems) {
                break;
            }

            $items[] = [
                'item_guid' => (string)($item->guid ?? $item->link),
                'title' => html_entity_decode(strip_tags((string)$item->title)),
                'description' => html_entity_decode(strip_tags((string)$item->description)),
                'link' => (string)$item->link,
                'pub_date' => $this->normalizePubDate((string)($item->pubDate ?? '')),
                'image_url' => $this->extractImageUrl($item, (string)$item->description),
            ];
        }

        if ($items === []) {
            throw new LocalizedException(__('RSS feed did not contain any items.'));
        }

        return $items;
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseVnExpressListing(string $payload, int $maxItems): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (!@$doc->loadHTML($payload)) {
            throw new LocalizedException(__('News listing returned invalid HTML.'));
        }

        $xpath = new \DOMXPath($doc);
        $articleNodes = $xpath->query("//article[contains(concat(' ', normalize-space(@class), ' '), ' item-news ')]");
        if (!$articleNodes instanceof \DOMNodeList || $articleNodes->length === 0) {
            throw new LocalizedException(__('News listing did not contain any article nodes.'));
        }

        $items = [];
        foreach ($articleNodes as $articleNode) {
            if (count($items) >= $maxItems) {
                break;
            }

            $linkNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' title-news ')]//a", $articleNode)->item(0);
            if (!$linkNode instanceof \DOMElement) {
                continue;
            }

            $link = trim((string)$linkNode->getAttribute('href'));
            if ($link === '') {
                continue;
            }
            if (str_starts_with($link, '/')) {
                $link = self::DEFAULT_ORIGIN . $link;
            }

            $title = trim((string)$linkNode->getAttribute('title'));
            if ($title === '') {
                $title = trim(html_entity_decode($linkNode->textContent));
            }
            if ($title === '') {
                continue;
            }

            $descriptionNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' description ')]", $articleNode)->item(0);
            $description = $descriptionNode instanceof \DOMNode
                ? trim(html_entity_decode(strip_tags($descriptionNode->textContent)))
                : '';

            $imgNode = $xpath->query(".//img", $articleNode)->item(0);
            $imageUrl = '';
            if ($imgNode instanceof \DOMElement) {
                $imageUrl = trim((string)($imgNode->getAttribute('data-src') ?: $imgNode->getAttribute('src')));
            }
            if ($imageUrl !== '' && str_starts_with($imageUrl, '//')) {
                $imageUrl = 'https:' . $imageUrl;
            }

            $dateNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' time ')]", $articleNode)->item(0);
            $pubDate = $dateNode instanceof \DOMNode ? $this->normalizePubDate(trim($dateNode->textContent)) : '';

            $items[] = [
                'item_guid' => $link,
                'title' => $title,
                'description' => $description,
                'link' => $link,
                'pub_date' => $pubDate,
                'image_url' => $imageUrl,
            ];
        }

        if ($items === []) {
            throw new LocalizedException(__('News listing did not contain usable items.'));
        }

        return $items;
    }

    private function normalizePubDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : '';
    }

    private function extractImageUrl(\SimpleXMLElement $item, string $description = ''): string
    {
        $namespaces = $item->getNamespaces(true);
        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);
            if (isset($media->thumbnail)) {
                $attributes = $media->thumbnail->attributes();

                return (string)($attributes['url'] ?? '');
            }
        }

        if (isset($item->enclosure)) {
            $attributes = $item->enclosure->attributes();
            if (isset($attributes['type']) && str_starts_with((string)$attributes['type'], 'image')) {
                return (string)($attributes['url'] ?? '');
            }
        }

        if ($description !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $matches)) {
            return (string)($matches[1] ?? '');
        }

        return '';
    }
}
