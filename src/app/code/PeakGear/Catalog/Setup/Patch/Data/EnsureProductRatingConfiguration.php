<?php
declare(strict_types=1);

namespace PeakGear\Catalog\Setup\Patch\Data;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Review\Setup\Patch\Data\InitReviewStatusesAndData;

class EnsureProductRatingConfiguration implements DataPatchInterface
{
    private const PRODUCT_ENTITY_CODE = 'product';
    private const DEFAULT_RATING_CODE = 'PeakGear Product Rating';
    private const DEFAULT_RATING_TITLE = 'Danh gia';
    private const DEFAULT_POSITION = 10;

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        try {
            $productEntityId = $this->getProductRatingEntityId();
            $storeIds = $this->getStoreIds();
            $ratingIdsToNormalize = $this->resolveRatingIdsToNormalize($productEntityId);

            foreach ($ratingIdsToNormalize as $ratingId) {
                $this->ensureRatingActive($ratingId);
                $this->ensureRatingStores($ratingId, $storeIds);
                $this->ensureRatingTitles($ratingId, $storeIds);
                $this->ensureRatingOptions($ratingId);
            }
        } finally {
            $connection->endSetup();
        }

        return $this;
    }

    public static function getDependencies()
    {
        return [
            InitReviewStatusesAndData::class,
        ];
    }

    public function getAliases()
    {
        return [];
    }

    /**
     * @return int[]
     */
    private function resolveRatingIdsToNormalize(int $productEntityId): array
    {
        $connection = $this->moduleDataSetup->getConnection();
        $ratingTable = $this->moduleDataSetup->getTable('rating');

        $select = $connection->select()
            ->from($ratingTable, ['rating_id', 'rating_code', 'is_active'])
            ->where('entity_id = ?', $productEntityId)
            ->order(['is_active DESC', 'position ASC', 'rating_id ASC']);

        $ratings = $connection->fetchAll($select);
        if ($ratings === []) {
            return [$this->createProductRating($productEntityId)];
        }

        $activeRatingIds = [];
        foreach ($ratings as $rating) {
            if ((int)$rating['is_active'] === 1) {
                $activeRatingIds[] = (int)$rating['rating_id'];
            }
        }

        if ($activeRatingIds !== []) {
            return $activeRatingIds;
        }

        $candidate = $this->pickCanonicalRating($ratings);

        return [(int)$candidate['rating_id']];
    }

    /**
     * @param array<int, array<string, mixed>> $ratings
     * @return array<string, mixed>
     */
    private function pickCanonicalRating(array $ratings): array
    {
        $preferredCodes = [
            'Quality',
            'Value',
            'Price',
            self::DEFAULT_RATING_CODE,
        ];

        foreach ($preferredCodes as $preferredCode) {
            foreach ($ratings as $rating) {
                if (strcasecmp((string)$rating['rating_code'], $preferredCode) === 0) {
                    return $rating;
                }
            }
        }

        return $ratings[0];
    }

    private function createProductRating(int $productEntityId): int
    {
        $connection = $this->moduleDataSetup->getConnection();
        $ratingTable = $this->moduleDataSetup->getTable('rating');

        $ratingCode = $this->buildUniqueRatingCode();

        $connection->insert($ratingTable, [
            'entity_id' => $productEntityId,
            'rating_code' => $ratingCode,
            'position' => self::DEFAULT_POSITION,
            'is_active' => 1,
        ]);

        return (int)$connection->lastInsertId($ratingTable);
    }

    private function ensureRatingActive(int $ratingId): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $ratingTable = $this->moduleDataSetup->getTable('rating');

        $connection->update(
            $ratingTable,
            ['is_active' => 1],
            ['rating_id = ?' => $ratingId]
        );
    }

    /**
     * @param int[] $storeIds
     */
    private function ensureRatingStores(int $ratingId, array $storeIds): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('rating_store');

        $select = $connection->select()
            ->from($table, ['store_id'])
            ->where('rating_id = ?', $ratingId);
        $existingStoreIds = array_map('intval', $connection->fetchCol($select));

        $rows = [];
        foreach ($storeIds as $storeId) {
            if (!in_array($storeId, $existingStoreIds, true)) {
                $rows[] = [
                    'rating_id' => $ratingId,
                    'store_id' => $storeId,
                ];
            }
        }

        if ($rows !== []) {
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * @param int[] $storeIds
     */
    private function ensureRatingTitles(int $ratingId, array $storeIds): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $ratingTable = $this->moduleDataSetup->getTable('rating');
        $titleTable = $this->moduleDataSetup->getTable('rating_title');

        $ratingCode = (string)$connection->fetchOne(
            $connection->select()
                ->from($ratingTable, ['rating_code'])
                ->where('rating_id = ?', $ratingId)
        );

        $titleValue = $ratingCode !== '' ? $ratingCode : self::DEFAULT_RATING_TITLE;

        $select = $connection->select()
            ->from($titleTable, ['store_id'])
            ->where('rating_id = ?', $ratingId);
        $existingStoreIds = array_map('intval', $connection->fetchCol($select));

        $rows = [];
        foreach ($storeIds as $storeId) {
            if (!in_array($storeId, $existingStoreIds, true)) {
                $rows[] = [
                    'rating_id' => $ratingId,
                    'store_id' => $storeId,
                    'value' => $titleValue,
                ];
            }
        }

        if ($rows !== []) {
            $connection->insertMultiple($titleTable, $rows);
        }
    }

    private function ensureRatingOptions(int $ratingId): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('rating_option');

        $select = $connection->select()
            ->from($table, ['value'])
            ->where('rating_id = ?', $ratingId);
        $existingValues = array_map('intval', $connection->fetchCol($select));

        $rows = [];
        for ($value = 1; $value <= 5; $value++) {
            if (!in_array($value, $existingValues, true)) {
                $rows[] = [
                    'rating_id' => $ratingId,
                    'code' => (string)$value,
                    'value' => $value,
                    'position' => $value,
                ];
            }
        }

        if ($rows !== []) {
            $connection->insertMultiple($table, $rows);
        }
    }

    private function getProductRatingEntityId(): int
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('rating_entity');

        $entityId = (int)$connection->fetchOne(
            $connection->select()
                ->from($table, ['entity_id'])
                ->where('entity_code = ?', self::PRODUCT_ENTITY_CODE)
        );

        if ($entityId <= 0) {
            throw new LocalizedException(
                __('Magento review rating entity "product" chưa tồn tại. Không thể chuẩn hóa rating.')
            );
        }

        return $entityId;
    }

    /**
     * @return int[]
     */
    private function getStoreIds(): array
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('store');

        return array_map(
            'intval',
            $connection->fetchCol(
                $connection->select()
                    ->from($table, ['store_id'])
                    ->order('store_id ASC')
            )
        );
    }

    private function buildUniqueRatingCode(): string
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('rating');
        $baseCode = self::DEFAULT_RATING_CODE;
        $suffix = 0;

        do {
            $candidate = $suffix === 0 ? $baseCode : sprintf('%s %d', $baseCode, $suffix);
            $exists = (int)$connection->fetchOne(
                $connection->select()
                    ->from($table, ['COUNT(*)'])
                    ->where('rating_code = ?', $candidate)
            );
            $suffix++;
        } while ($exists > 0);

        return $candidate;
    }
}
