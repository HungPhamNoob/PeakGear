<?php
declare(strict_types=1);

namespace Vendor\Shipping\Service;

use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class QuoteRegionNormalizer
{
    private const COUNTRY_ID = 'VN';

    private ?array $regionIndex = null;

    public function __construct(
        private readonly CollectionFactory $regionCollectionFactory,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    public function normalize(Quote $quote): bool
    {
        $changed = false;

        foreach ([$quote->getShippingAddress(), $quote->getBillingAddress()] as $address) {
            if (!$address || strtoupper((string)$address->getCountryId()) !== self::COUNTRY_ID) {
                continue;
            }

            $region = $this->resolveRegion(
                (string)$address->getCity(),
                (string)$address->getRegion(),
                (string)$address->getRegionCode(),
                (int)$address->getRegionId()
            );

            if ($region === null) {
                continue;
            }

            $regionId = (int)$region['id'];
            $regionName = (string)$region['name'];
            $regionCode = (string)$region['code'];

            if ((int)$address->getRegionId() === $regionId
                && (string)$address->getRegion() === $regionName
                && (string)$address->getRegionCode() === $regionCode) {
                continue;
            }

            $address->setRegionId($regionId);
            $address->setRegion($regionName);
            $address->setRegionCode($regionCode);
            $changed = true;
        }

        if ($changed) {
            $this->cartRepository->save($quote);
        }

        return $changed;
    }

    private function resolveRegion(string $city, string $regionName, string $regionCode, int $regionId): ?array
    {
        $index = $this->getRegionIndex();

        foreach ([$city, $regionName, $regionCode] as $candidate) {
            $normalized = $this->normalizeName($candidate);
            if ($normalized !== '' && isset($index['by_name'][$normalized])) {
                return $index['by_name'][$normalized];
            }
        }

        return $regionId > 0 && isset($index['by_id'][$regionId])
            ? $index['by_id'][$regionId]
            : null;
    }

    private function getRegionIndex(): array
    {
        if ($this->regionIndex !== null) {
            return $this->regionIndex;
        }

        $byId = [];
        $byName = [];
        $regions = $this->regionCollectionFactory->create()
            ->addCountryFilter(self::COUNTRY_ID)
            ->getItems();

        foreach ($regions as $region) {
            $data = [
                'id' => (int)$region->getRegionId(),
                'name' => (string)$region->getName(),
                'code' => (string)$region->getCode(),
            ];

            if ($data['id'] <= 0 || $data['name'] === '') {
                continue;
            }

            $byId[$data['id']] = $data;

            foreach ([$data['name'], $data['code']] as $value) {
                $normalized = $this->normalizeName($value);
                if ($normalized !== '' && !isset($byName[$normalized])) {
                    $byName[$normalized] = $data;
                }
            }
        }

        return $this->regionIndex = [
            'by_id' => $byId,
            'by_name' => $byName,
        ];
    }

    private function normalizeName(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        $normalized = str_replace('đ', 'd', $normalized);

        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $normalized);
            if (is_string($transliterated)) {
                $normalized = strtolower($transliterated);
            }
        } else {
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            if ($transliterated !== false) {
                $normalized = strtolower($transliterated);
            }
        }

        $normalized = preg_replace('/^(thanh\s*pho|tp\.?|tinh)\s+/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        $aliases = [
            'tp hcm' => 'ho chi minh',
            'hcm' => 'ho chi minh',
            'sai gon' => 'ho chi minh',
            'tphcm' => 'ho chi minh',
            'hanoi' => 'ha noi',
            'thua thien hue' => 'hue',
            'ba ria vung tau' => 'vung tau',
        ];

        return $aliases[$normalized] ?? $normalized;
    }
}
