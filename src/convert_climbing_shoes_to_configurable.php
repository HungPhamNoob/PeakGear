<?php
declare(strict_types=1);

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as ProductAttribute;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as ConfigurableOptionsFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

/** @var State $appState */
$appState = $objectManager->get(State::class);
try {
    $appState->setAreaCode(Area::AREA_ADMINHTML);
} catch (LocalizedException $exception) {
    // Area code is already set.
}

$options = getopt('', [
    'category::',
    'sizes::',
    'qty::',
    'disable-original::',
    'include-children::',
    'dry-run::',
    'parent-sku-suffix::',
]);

$categoryName = trim((string)($options['category'] ?? 'Giày Leo Núi'));
$sizes = array_values(array_filter(array_map('trim', explode(',', (string)($options['sizes'] ?? '43,44,45,46,47')))));
$qtyPerSize = max(0, (int)($options['qty'] ?? 30));
$disableOriginal = parseBoolOption($options['disable-original'] ?? '1');
$includeChildren = parseBoolOption($options['include-children'] ?? '1');
$dryRun = parseBoolOption($options['dry-run'] ?? '0');
$parentSkuSuffix = trim((string)($options['parent-sku-suffix'] ?? '-config'));
$sizeAttributeCode = 'shoes_sizes';

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
/** @var ProductCollectionFactory $productCollectionFactory */
$productCollectionFactory = $objectManager->get(ProductCollectionFactory::class);
/** @var CategoryCollectionFactory $categoryCollectionFactory */
$categoryCollectionFactory = $objectManager->get(CategoryCollectionFactory::class);
/** @var CategoryLinkManagementInterface $categoryLinkManagement */
$categoryLinkManagement = $objectManager->get(CategoryLinkManagementInterface::class);
/** @var StockRegistryInterface $stockRegistry */
$stockRegistry = $objectManager->get(StockRegistryInterface::class);
/** @var EavConfig $eavConfig */
$eavConfig = $objectManager->get(EavConfig::class);
/** @var ConfigurableOptionsFactory $configurableOptionsFactory */
$configurableOptionsFactory = $objectManager->get(ConfigurableOptionsFactory::class);
/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
$configurableType = $objectManager->get(Configurable::class);

echo "=== Convert climbing shoes to configurable ===\n";
echo "Category: {$categoryName}\n";
echo 'Sizes: ' . implode(', ', $sizes) . "\n";
echo "Qty each size: {$qtyPerSize}\n";
echo 'Disable original simple: ' . ($disableOriginal ? 'yes' : 'no') . "\n";
echo 'Include child categories: ' . ($includeChildren ? 'yes' : 'no') . "\n";
echo 'Dry run: ' . ($dryRun ? 'yes' : 'no') . "\n\n";

$sizeAttribute = requireExistingSizeAttribute($eavConfig, $sizeAttributeCode);
$optionMap = requireExistingSizeOptions($sizeAttribute, $sizes);

$category = findCategoryByName($categoryCollectionFactory, $categoryName);
if ($category === null) {
    fail("Category `{$categoryName}` was not found.");
}

$categoryIds = [(int)$category->getId()];
if ($includeChildren) {
    $categoryIds = array_values(array_unique(array_merge($categoryIds, getDescendantCategoryIds($categoryCollectionFactory, (string)$category->getPath()))));
}

$sourceProducts = $productCollectionFactory->create();
$sourceProducts->addAttributeToSelect([
    'name',
    'sku',
    'price',
    'status',
    'visibility',
    'type_id',
    'description',
    'short_description',
    'image',
    'small_image',
    'thumbnail',
    'swatch_image',
    'url_key',
    'meta_title',
    'meta_keyword',
    'meta_description',
    'tax_class_id',
    'weight',
]);
$sourceProducts->addCategoriesFilter(['in' => $categoryIds]);
$sourceProducts->addAttributeToFilter('type_id', Type::TYPE_SIMPLE);

if ((int)$sourceProducts->getSize() === 0) {
    echo "No simple products found in the target category.\n";
    exit(0);
}

$processedCount = 0;
$skippedCount = 0;
$errorCount = 0;

foreach ($sourceProducts as $sourceProduct) {
    $sourceSku = (string)$sourceProduct->getSku();

    if (shouldSkipSourceProduct($sourceProduct, $sizes, $parentSkuSuffix)) {
        $skippedCount++;
        echo "[skip] {$sourceSku} is already a generated child/parent candidate.\n";
        continue;
    }

    $parentIds = $configurableType->getParentIdsByChild((int)$sourceProduct->getId());
    if (!empty($parentIds)) {
        $skippedCount++;
        echo "[skip] {$sourceSku} is already linked to a configurable product.\n";
        continue;
    }

    try {
        $sourceProduct = loadProductBySku($productRepository, $sourceSku) ?: $sourceProduct;

        $parentSku = buildParentSku($sourceSku, $parentSkuSuffix);
        $categoryIdsForProduct = array_map('intval', $sourceProduct->getCategoryIds() ?: $categoryIds);
        $websiteIds = array_map('intval', $sourceProduct->getWebsiteIds() ?: [$storeManager->getWebsite()->getId()]);

        $children = [];
        $attributeValues = [];

        foreach ($sizes as $sizeLabel) {
            if (!isset($optionMap[$sizeLabel])) {
                throw new LocalizedException(__("Missing option id for size `%1`.", $sizeLabel));
            }

            $childSku = $sourceSku . '-' . $sizeLabel;
            $child = upsertChildProduct(
                $productRepository,
                $sourceProduct,
                $childSku,
                $sizeAttributeCode,
                (int)$optionMap[$sizeLabel],
                $sizeLabel,
                $qtyPerSize,
                $categoryIdsForProduct,
                $websiteIds,
                $stockRegistry,
                $objectManager,
                $dryRun
            );

            $children[] = $child;
            $attributeValues[] = [
                'label' => $sizeAttribute->getStoreLabel(),
                'attribute_id' => (int)$sizeAttribute->getId(),
                'value_index' => (int)$optionMap[$sizeLabel],
            ];
        }

        upsertParentConfigurable(
            $productRepository,
            $configurableOptionsFactory,
            $categoryLinkManagement,
            $sourceProduct,
            $parentSku,
            $parentSkuSuffix,
            $sizeAttribute,
            $attributeValues,
            $children,
            $categoryIdsForProduct,
            $websiteIds,
            $dryRun
        );

        if ($disableOriginal) {
            disableOriginalProduct($productRepository, $sourceProduct, $dryRun);
        }

        $processedCount++;
        echo "[ok] {$sourceSku} -> {$parentSku}\n";
    } catch (\Throwable $throwable) {
        $errorCount++;
        echo "[error] {$sourceSku}: {$throwable->getMessage()}\n";
    }
}

echo "\n=== Summary ===\n";
echo "Processed: {$processedCount}\n";
echo "Skipped: {$skippedCount}\n";
echo "Errors: {$errorCount}\n";

if (!$dryRun) {
    echo "\nNext steps:\n";
    echo "php bin/magento indexer:reindex\n";
    echo "php bin/magento cache:flush\n";
}

function parseBoolOption(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y'], true);
}

function fail(string $message): void
{
    fwrite(STDERR, "[fatal] {$message}\n");
    exit(1);
}

function requireExistingSizeAttribute(EavConfig $eavConfig, string $attributeCode): ProductAttribute
{
    /** @var ProductAttribute $attribute */
    $attribute = $eavConfig->getAttribute(Product::ENTITY, $attributeCode);
    if (!$attribute || !(int)$attribute->getId()) {
        fail("Required attribute `{$attributeCode}` does not exist. Create it in Magento before running this script.");
    }

    return $attribute;
}

function requireExistingSizeOptions(ProductAttribute $attribute, array $sizes): array
{
    $optionMap = getOptionMap($attribute);
    $missingSizes = [];

    foreach ($sizes as $sizeLabel) {
        if (!isset($optionMap[$sizeLabel])) {
            $missingSizes[] = $sizeLabel;
        }
    }

    if ($missingSizes !== []) {
        fail(
            'Missing size options for attribute `'
            . $attribute->getAttributeCode()
            . '`: '
            . implode(', ', $missingSizes)
            . '. Add them in Magento before running this script.'
        );
    }

    return $optionMap;
}

function getOptionMap(ProductAttribute $attribute): array
{
    $optionMap = [];
    foreach ($attribute->getOptions() as $option) {
        $label = trim((string)$option->getLabel());
        $value = (string)$option->getValue();
        if ($label === '' || $value === '') {
            continue;
        }
        $optionMap[$label] = (int)$value;
    }

    return $optionMap;
}

function findCategoryByName(CategoryCollectionFactory $categoryCollectionFactory, string $categoryName): ?\Magento\Catalog\Model\Category
{
    $collection = $categoryCollectionFactory->create();
    $collection->addAttributeToSelect(['name', 'path']);

    foreach ($collection as $category) {
        if (mb_strtolower(trim((string)$category->getName())) === mb_strtolower($categoryName)) {
            return $category;
        }
    }

    return null;
}

function getDescendantCategoryIds(CategoryCollectionFactory $categoryCollectionFactory, string $categoryPath): array
{
    $collection = $categoryCollectionFactory->create();
    $collection->addAttributeToSelect('name');
    $collection->addFieldToFilter('path', ['like' => $categoryPath . '/%']);

    return array_map('intval', $collection->getAllIds());
}

function shouldSkipSourceProduct(Product $product, array $sizes, string $parentSkuSuffix): bool
{
    $sku = (string)$product->getSku();
    if (str_ends_with($sku, $parentSkuSuffix)) {
        return true;
    }

    foreach ($sizes as $size) {
        if (str_ends_with($sku, '-' . $size)) {
            return true;
        }
    }

    return false;
}

function buildParentSku(string $sourceSku, string $parentSkuSuffix): string
{
    return $sourceSku . $parentSkuSuffix;
}

function upsertChildProduct(
    ProductRepositoryInterface $productRepository,
    Product $sourceProduct,
    string $childSku,
    string $sizeAttributeCode,
    int $sizeOptionId,
    string $sizeLabel,
    int $qtyPerSize,
    array $categoryIds,
    array $websiteIds,
    StockRegistryInterface $stockRegistry,
    \Magento\Framework\ObjectManagerInterface $objectManager,
    bool $dryRun
): Product {
    $child = loadProductBySku($productRepository, $childSku) ?: $objectManager->create(Product::class);

    $child->setStoreId(0);
    $child->setAttributeSetId((int)$sourceProduct->getAttributeSetId());
    $child->setTypeId(Type::TYPE_SIMPLE);
    $child->setSku($childSku);
    $child->setName((string)$sourceProduct->getName() . ' - Size ' . $sizeLabel);
    $child->setPrice((float)$sourceProduct->getPrice());
    $child->setStatus(Status::STATUS_ENABLED);
    $child->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
    $child->setWebsiteIds($websiteIds);
    $child->setCategoryIds($categoryIds);
    $child->setTaxClassId((int)$sourceProduct->getTaxClassId());
    $child->setWeight((float)($sourceProduct->getWeight() ?: 1));
    $child->setDescription((string)$sourceProduct->getDescription());
    $child->setShortDescription((string)$sourceProduct->getShortDescription());
    $child->setData('meta_title', $sourceProduct->getData('meta_title'));
    $child->setData('meta_keyword', $sourceProduct->getData('meta_keyword'));
    $child->setData('meta_description', $sourceProduct->getData('meta_description'));
    $child->setData('url_key', buildChildUrlKey($sourceProduct, $sizeLabel));
    $child->setData('image', $sourceProduct->getData('image'));
    $child->setData('small_image', $sourceProduct->getData('small_image'));
    $child->setData('thumbnail', $sourceProduct->getData('thumbnail'));
    $child->setData('swatch_image', $sourceProduct->getData('swatch_image'));
    $child->setData($sizeAttributeCode, $sizeOptionId);
    $child->setStockData([
        'use_config_manage_stock' => 0,
        'manage_stock' => 1,
        'qty' => $qtyPerSize,
        'is_in_stock' => 1,
    ]);

    if ($dryRun) {
        echo "[dry-run] Would save child {$childSku} (size {$sizeLabel}).\n";
        return $child;
    }

    $child = $productRepository->save($child);
    updateStockForSku($stockRegistry, $objectManager, $childSku, $qtyPerSize);

    return loadProductBySku($productRepository, $childSku) ?: $child;
}

function buildChildUrlKey(Product $sourceProduct, string $sizeLabel): string
{
    $baseUrlKey = trim((string)$sourceProduct->getData('url_key'));
    if ($baseUrlKey === '') {
        $baseUrlKey = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string)$sourceProduct->getName())) ?: 'product';
        $baseUrlKey = trim($baseUrlKey, '-');
    }

    return $baseUrlKey . '-size-' . $sizeLabel;
}

function upsertParentConfigurable(
    ProductRepositoryInterface $productRepository,
    ConfigurableOptionsFactory $configurableOptionsFactory,
    CategoryLinkManagementInterface $categoryLinkManagement,
    Product $sourceProduct,
    string $parentSku,
    string $parentSkuSuffix,
    ProductAttribute $sizeAttribute,
    array $attributeValues,
    array $children,
    array $categoryIds,
    array $websiteIds,
    bool $dryRun
): void {
    $parent = loadProductBySku($productRepository, $parentSku)
        ?: \Magento\Framework\App\ObjectManager::getInstance()->create(Product::class);

    if (!$parent->getId()) {
        $parent->setStoreId(0);
        $parent->setSku($parentSku);
    }

    $parent->setAttributeSetId((int)$sourceProduct->getAttributeSetId());
    $parent->setTypeId(Configurable::TYPE_CODE);
    $parent->setName((string)$sourceProduct->getName());
    $parent->setPrice((float)$sourceProduct->getPrice());
    $parent->setStatus(Status::STATUS_ENABLED);
    $parent->setVisibility(Visibility::VISIBILITY_BOTH);
    $parent->setWebsiteIds($websiteIds);
    $parent->setCategoryIds($categoryIds);
    $parent->setTaxClassId((int)$sourceProduct->getTaxClassId());
    $parent->setDescription((string)$sourceProduct->getDescription());
    $parent->setShortDescription((string)$sourceProduct->getShortDescription());
    $parent->setData('meta_title', $sourceProduct->getData('meta_title'));
    $parent->setData('meta_keyword', $sourceProduct->getData('meta_keyword'));
    $parent->setData('meta_description', $sourceProduct->getData('meta_description'));
    $parent->setData('url_key', buildParentUrlKey($sourceProduct, $parentSkuSuffix));
    $parent->setData('image', $sourceProduct->getData('image'));
    $parent->setData('small_image', $sourceProduct->getData('small_image'));
    $parent->setData('thumbnail', $sourceProduct->getData('thumbnail'));
    $parent->setData('swatch_image', $sourceProduct->getData('swatch_image'));
    $parent->setStockData([
        'use_config_manage_stock' => 1,
        'is_in_stock' => 1,
    ]);

    $configurableAttributesData = [[
        'attribute_id' => (int)$sizeAttribute->getId(),
        'code' => $sizeAttribute->getAttributeCode(),
        'label' => $sizeAttribute->getStoreLabel(),
        'position' => '0',
        'values' => $attributeValues,
    ]];

    $configurableOptions = $configurableOptionsFactory->create($configurableAttributesData);
    $extensionAttributes = $parent->getExtensionAttributes();
    if ($extensionAttributes === null) {
        $extensionAttributes = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Catalog\Api\Data\ProductExtensionFactory::class)
            ->create();
    }

    $extensionAttributes->setConfigurableProductOptions($configurableOptions);
    $extensionAttributes->setConfigurableProductLinks(array_values(array_filter(array_map(
        static fn(Product $child): ?int => $child->getId() ? (int)$child->getId() : null,
        $children
    ))));
    $parent->setExtensionAttributes($extensionAttributes);

    if ($dryRun) {
        echo "[dry-run] Would save configurable {$parentSku} with " . count($children) . " children.\n";
        return;
    }

    $productRepository->save($parent);
    $categoryLinkManagement->assignProductToCategories($parentSku, $categoryIds);
}

function buildParentUrlKey(Product $sourceProduct, string $parentSkuSuffix): string
{
    $baseUrlKey = trim((string)$sourceProduct->getData('url_key'));
    if ($baseUrlKey === '') {
        $baseUrlKey = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string)$sourceProduct->getName())) ?: 'product';
        $baseUrlKey = trim($baseUrlKey, '-');
    }

    return $baseUrlKey . preg_replace('/[^a-z0-9-]+/i', '-', strtolower($parentSkuSuffix));
}

function disableOriginalProduct(
    ProductRepositoryInterface $productRepository,
    Product $sourceProduct,
    bool $dryRun
): void {
    $sourceProduct->setStatus(Status::STATUS_DISABLED);
    $sourceProduct->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);

    if ($dryRun) {
        echo "[dry-run] Would disable original simple {$sourceProduct->getSku()}.\n";
        return;
    }

    $productRepository->save($sourceProduct);
}

function loadProductBySku(ProductRepositoryInterface $productRepository, string $sku): ?Product
{
    try {
        $product = $productRepository->get($sku, false, null, true);
        return $product instanceof Product ? $product : null;
    } catch (NoSuchEntityException $exception) {
        return null;
    }
}

function updateStockForSku(
    StockRegistryInterface $stockRegistry,
    \Magento\Framework\ObjectManagerInterface $objectManager,
    string $sku,
    int $qty
): void {
    $stockItem = $stockRegistry->getStockItemBySku($sku);
    $stockItem->setUseConfigManageStock(false);
    $stockItem->setManageStock(true);
    $stockItem->setQty($qty);
    $stockItem->setIsInStock($qty > 0);
    $stockRegistry->updateStockItemBySku($sku, $stockItem);

    if (
        interface_exists(SourceItemsSaveInterface::class)
        && interface_exists(SourceItemInterface::class)
        && class_exists(SourceItemInterfaceFactory::class)
    ) {
        /** @var SourceItemsSaveInterface $sourceItemsSave */
        $sourceItemsSave = $objectManager->get(SourceItemsSaveInterface::class);
        /** @var SourceItemInterfaceFactory $sourceItemFactory */
        $sourceItemFactory = $objectManager->get(SourceItemInterfaceFactory::class);

        $sourceItem = $sourceItemFactory->create();
        $sourceItem->setSourceCode('default');
        $sourceItem->setSku($sku);
        $sourceItem->setQuantity((float)$qty);
        $sourceItem->setStatus($qty > 0 ? SourceItemInterface::STATUS_IN_STOCK : SourceItemInterface::STATUS_OUT_OF_STOCK);
        $sourceItemsSave->execute([$sourceItem]);
    }
}
