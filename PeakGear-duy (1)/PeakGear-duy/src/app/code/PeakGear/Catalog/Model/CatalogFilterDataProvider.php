<?php
declare(strict_types=1);

namespace PeakGear\Catalog\Model;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;

/**
 * Centralizes lookup of storefront filter attributes and brand options.
 */
class CatalogFilterDataProvider
{
    public function __construct(
        private readonly EavConfig $eavConfig,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return list<array{code:string, label:string, options:list<array{id:int, label:string}>}>
     */
    public function getFilterableAttributes(): array
    {
        $attributes = [];
        $filterableCodes = ['color'];

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['ea' => $this->resourceConnection->getTableName('eav_attribute')],
                ['attribute_code', 'frontend_label']
            )
            ->join(
                ['cea' => $this->resourceConnection->getTableName('catalog_eav_attribute')],
                'ea.attribute_id = cea.attribute_id',
                []
            )
            ->where('cea.is_filterable = ?', 1)
            ->where('ea.frontend_input IN (?)', ['select', 'multiselect'])
            ->where('ea.entity_type_id = ?', 4)
            ->where('ea.attribute_code NOT IN (?)', ['manufacturer', 'status', 'visibility', 'tax_class_id']);

        foreach ($connection->fetchAll($select) as $row) {
            if (!in_array($row['attribute_code'], $filterableCodes, true)) {
                $filterableCodes[] = $row['attribute_code'];
            }
        }

        foreach ($filterableCodes as $code) {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $code);
            if (!$attribute || !$attribute->getId() || !$attribute->usesSource()) {
                continue;
            }

            $options = [];
            foreach ($attribute->getSource()->getAllOptions(false) as $option) {
                if (empty($option['value']) || empty($option['label'])) {
                    continue;
                }

                $options[] = [
                    'id' => (int)$option['value'],
                    'label' => (string)$option['label'],
                ];
            }

            if ($options !== []) {
                $attributes[] = [
                    'code' => $code,
                    'label' => (string)($attribute->getStoreLabel() ?: $attribute->getFrontendLabel()),
                    'options' => $options,
                ];
            }
        }

        return $attributes;
    }

    /**
     * @return list<array{id:int, name:string}>
     */
    public function getBrands(): array
    {
        $brands = [];
        $attribute = $this->eavConfig->getAttribute('catalog_product', 'manufacturer');
        if (!$attribute || !$attribute->getId() || !$attribute->usesSource()) {
            return $brands;
        }

        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            if (empty($option['value']) || empty($option['label'])) {
                continue;
            }

            $brands[] = [
                'id' => (int)$option['value'],
                'name' => (string)$option['label'],
            ];
        }

        return $brands;
    }
}
