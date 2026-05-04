<?php
declare(strict_types=1);

namespace Vendor\Shipping\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

class FeeCache
{
    private const CACHE_PREFIX = 'vendor_shipping_ghtk_fee_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json
    ) {
    }

    public function load(string $key): ?array
    {
        $payload = $this->cache->load(self::CACHE_PREFIX . $key);
        if ($payload === false || $payload === '') {
            return null;
        }

        try {
            $data = $this->json->unserialize($payload);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    public function save(string $key, array $value, int $ttl): void
    {
        if ($ttl <= 0) {
            return;
        }

        $this->cache->save(
            $this->json->serialize($value),
            self::CACHE_PREFIX . $key,
            [],
            $ttl
        );
    }
}
