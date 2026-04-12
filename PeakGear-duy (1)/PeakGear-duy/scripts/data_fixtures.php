<?php
/**
 * PeakGear Data Fixtures Script
 * Tạo 44 danh mục + 330+ sản phẩm demo
 *
 * Chạy trong Docker container:
 * docker exec peakgear_php php /var/www/html/bin/magento dev:console
 * Hoặc:
 * docker exec peakgear_php php /var/www/html/app/code/Vendor/DataFixtures/data_fixtures.php
 */

use Magento\Framework\App\Bootstrap;
use Magento\Catalog\Api\CategoryManagementInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Type;
use Magento\CatalogInventory\Api\StockRegistryInterface;

require_once __DIR__ . '/../../../../bootstrap.php';

/** @var \Magento\Framework\App\ObjectManager $objectManager */
$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

/** @var \Magento\Store\Model\App\Emulation $emulation */
$appState = $objectManager->get(\Magento\Framework\App\State::class);
$appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

$categoryFactory = $objectManager->get(\Magento\Catalog\Model\CategoryFactory::class);
$productFactory  = $objectManager->get(\Magento\Catalog\Model\ProductFactory::class);
$stockRegistry   = $objectManager->get(StockRegistryInterface::class);

$rootCategoryId = 2; // Default Magento root category

// ========================================================
// CATEGORY STRUCTURE (44 categories: 11 parent + 33 child)
// ========================================================
$categories = [
    'Ba lô & Túi xách Outdoor' => [
        'Ba lô leo núi (>40L)',
        'Ba lô trekking (20-40L)',
        'Túi đeo chéo & Waist pack',
    ],
    'Lều & Trại cắm trại' => [
        'Lều solo & đôi (1-2 người)',
        'Lều gia đình (3-4 người)',
        'Lều 4 mùa & núi cao',
    ],
    'Quần áo Outdoor' => [
        'Áo khoác & Shell jacket',
        'Base layer & Mid layer',
        'Quần trekking & Softshell',
    ],
    'Thiết bị kỹ thuật số' => [
        'GPS & Định vị',
        'Đèn đầu & Đèn cắm trại',
        'Pin sạc & Power bank',
    ],
    'Dụng cụ leo núi' => [
        'Dây leo & Móc carabiner',
        'Gậy trekking',
        'Dây đai & Harness',
    ],
    'Thiết bị nấu ăn' => [
        'Bếp gas & Bếp cồn',
        'Nồi mess kit & Bát đĩa',
        'Bình nước & Máy lọc nước',
    ],
    'Trang phục leo núi mùa lạnh' => [
        'Áo lông vũ & Fleece',
        'Găng tay & Mũ len',
        'Mặt nạ tuyết & Balaclava',
    ],
    'An toàn & Sơ cứu' => [
        'Bộ sơ cứu outdoor',
        'Thiết bị tín hiệu khẩn cấp',
        'Dụng cụ định hướng',
    ],
    'Phụ kiện & Đồ tiêu hao' => [
        'Vớ trekking & Mũ outdoor',
        'Kính UV & Kem chống nắng',
        'Băng keo & Dụng cụ sửa chữa',
    ],
    'Camp & Bivouac' => [
        'Túi ngủ & Đệm ngủ',
        'Võng & Ghế gấp',
        'Đèn lantern & Nến',
    ],
    'Giày & Sandal Outdoor' => [
        'Giày leo núi cổ cao',
        'Giày trekking cổ thấp',
        'Sandal outdoor & Camp shoes',
    ],
];

// Sample brands by category group
$brandsByGroup = [
    'ba lo'       => ['Osprey', 'Deuter', 'Gregory', 'Black Diamond', 'REI'],
    'leu'         => ['MSR', 'Big Agnes', 'Naturehike', 'Hilleberg', 'Mountain Hardwear'],
    'quan ao'     => ["Arc'teryx", 'Patagonia', 'Mammut', 'Rab', 'The North Face'],
    'thiet bi'    => ['Garmin', 'Petzl', 'Black Diamond', 'Anker', 'Silva'],
    'leo nui'     => ['Black Diamond', 'Petzl', 'Wild Country', 'Mammut', 'Edelrid'],
    'nau an'      => ['MSR', 'Snow Peak', 'Primus', 'Jetboil', 'GSI Outdoors'],
    'mua lanh'    => ['Rab', 'Patagonia', 'Mountain Equipment', 'PHD', 'Fjallraven'],
    'so cuu'      => ['Adventure Medical', 'Silva', 'ACR', 'Lifesystems', 'Pocket Radar'],
    'phu kien'    => ['Darn Tough', 'Julbo', 'Sun Bum', 'Gear Aid', 'Sea to Summit'],
    'camp'        => ['Therm-a-Rest', 'Western Mountaineering', 'Eagles Nest', 'Helinox', 'BioLite'],
    'giay'        => ['Salomon', 'Lowa', 'Merrell', 'Scarpa', 'La Sportiva'],
];

// Products template per child category
$productTemplates = [
    'Ba lô leo núi (>40L)' => [
        ['Osprey Aether 65 Ba lô leo núi', 6800000, 'Capacity: 65L, Weight: 2.3kg, Waterproof: IPX4'],
        ['Deuter Aircontact Core 60 Ba lô', 5500000, 'Capacity: 60L, Weight: 2.1kg, Aircontact back system'],
        ['Gregory Baltoro 75 Expedition', 7200000, 'Capacity: 75L, Weight: 2.5kg, Response A3 hip belt'],
        ['Black Diamond Halo 40 Backpack', 4200000, 'Capacity: 40L, Weight: 1.4kg, 3DF air mesh back'],
        ['Osprey Kestrel 48 Hiking Pack', 4800000, 'Capacity: 48L, Weight: 1.5kg, IsoForm hipbelt'],
        ['Deuter Futura Vario 50 Ba lô trekking', 5200000, 'Capacity: 50L, Weight: 1.75kg, Vari-Quick back system'],
        ['REI Co-op Traverse 70 Pack', 3900000, 'Capacity: 70L, Weight: 2.2kg, A3 hipbelt'],
        ['Osprey Ariel 55 Women\'s Pack', 5100000, 'For women, Capacity: 55L, Weight: 2.0kg'],
        ['Gregory Deva 60 Women Backpack', 5600000, 'Women\'s fit, Capacity: 60L, GeoFit hipbelt'],
        ['Deuter Aviant Voyager 65 Reise', 6100000, 'Detachable daypack, Capacity: 65L'],
    ],
    'Ba lô trekking (20-40L)' => [
        ['Osprey Exos 38 Ultralight Pack', 3200000, 'Capacity: 38L, Weight: 958g, Ultralight'],
        ['Deuter Speed Lite 30 Rucksack', 2800000, 'Capacity: 30L, Weight: 680g, Speed hiking'],
        ['Black Diamond Bullet 16 Pack', 2100000, 'Capacity: 16L, Minimalist, Weight: 290g'],
        ['Osprey Talon 22 Day Hike Pack', 2500000, 'Capacity: 22L, AirSpeed back panel'],
        ['Gregory Zulu 30 Hiking Pack', 2900000, 'Capacity: 30L, Swiftcurrent suspension'],
        ['Salomon Trailblazer 30 Backpack', 2400000, 'Capacity: 30L, Weight: 550g'],
        ['The North Face Banchee 50 Pack', 4100000, 'Capacity: 50L, FlexVent suspension'],
        ['Mammut Lithium 30 Hiking Pack', 3100000, 'Capacity: 30L, Weight: 820g'],
        ['Osprey Daylite Plus Daypack', 1800000, 'Capacity: 20L, Lightweight AirSpeed panel'],
        ['Deuter Hiking 28 Day Pack', 2200000, 'Capacity: 28L, Aircomfort back system'],
    ],
    'default' => [
        ['Sản phẩm Outdoor Premium %s - Size S', null, 'Chất lượng cao, phù hợp cho trekking và leo núi'],
        ['Sản phẩm Outdoor Premium %s - Size M', null, 'Chất lượng cao, phù hợp cho trekking và leo núi'],
        ['Sản phẩm Outdoor Premium %s - Size L', null, 'Chất lượng cao, phù hợp cho trekking và leo núi'],
        ['Sản phẩm Outdoor Premium %s - Size XL', null, 'Chất lượng cao, phù hợp cho trekking và leo núi'],
        ['Outdoor Gear %s - Standard', null, 'Tiêu chuẩn quốc tế, bền bỉ trong mọi điều kiện thời tiết'],
        ['Outdoor Gear %s - Pro', null, 'Phiên bản Pro cao cấp dành cho dân chuyên'],
        ['Outdoor Gear %s - Ultralight', null, 'Siêu nhẹ, tiết kiệm trọng lượng cho đường dài'],
        ['Outdoor Gear %s - Waterproof', null, 'Chống nước IPX5, lý tưởng cho mùa mưa'],
        ['Outdoor Gear %s - 4 Season', null, 'Phù hợp 4 mùa, chịu lạnh đến -20°C'],
        ['Outdoor Gear %s - Compact', null, 'Thiết kế gọn nhẹ, dễ mang theo'],
    ],
];

echo "=== PeakGear Data Fixtures ===\n";

// Create parent categories
$createdCategories = []; // parent_name => parent_id
$childCategoryMap = [];  // child_name => child_id

$i = 0;
foreach ($categories as $parentName => $children) {
    $i++;
    // Create parent category
    /** @var \Magento\Catalog\Model\Category $parentCat */
    $parentCat = $categoryFactory->create();
    $parentCat->setName($parentName)
              ->setIsActive(true)
              ->setParentId($rootCategoryId)
              ->setPath('1/' . $rootCategoryId)
              ->setIncludeInMenu(true)
              ->setDisplayMode('PRODUCTS')
              ->setData('description', 'Danh mục ' . $parentName . ' - PeakGear outdoor shop')
              ->setData('meta_title', $parentName . ' - PeakGear')
              ->setData('meta_description', 'Mua ' . $parentName . ' chính hãng tại PeakGear')
              ->setData('url_key', strtolower(str_replace([' ', '&', ',', '/'], ['-', '', '', '-'], iconv('UTF-8', 'ASCII//TRANSLIT', $parentName))) . '-' . $i);

    try {
        $parentCat->save();
        $createdCategories[$parentName] = $parentCat->getId();
        echo "✅ Tạo danh mục cha: [{$parentCat->getId()}] {$parentName}\n";

        // Create child categories
        $j = 0;
        foreach ($children as $childName) {
            $j++;
            $childCat = $categoryFactory->create();
            $childCat->setName($childName)
                     ->setIsActive(true)
                     ->setParentId($parentCat->getId())
                     ->setPath('1/' . $rootCategoryId . '/' . $parentCat->getId())
                     ->setIncludeInMenu(true)
                     ->setDisplayMode('PRODUCTS')
                     ->setData('description', 'Danh mục ' . $childName)
                     ->setData('url_key', strtolower(str_replace([' ', '&', ',', '/'], ['-', '', '', '-'], iconv('UTF-8', 'ASCII//TRANSLIT', $childName))) . '-' . $i . $j);

            $childCat->save();
            $childCategoryMap[$childName] = $childCat->getId();
            echo "  📂 Tạo danh mục con: [{$childCat->getId()}] {$childName}\n";
        }
    } catch (\Exception $e) {
        echo "❌ Lỗi tạo danh mục: {$e->getMessage()}\n";
    }
}

echo "\n=== Tạo sản phẩm demo ===\n";

$productCount = 0;
$skuPrefix = 'PG-';

foreach ($childCategoryMap as $childName => $childCatId) {
    $templates = $productTemplates[$childName] ?? $productTemplates['default'];

    foreach ($templates as $idx => $tpl) {
        [$tplName, $tplPrice, $tplDesc] = $tpl;

        // Replace %s placeholder with child name abbreviation
        $abbr = mb_strtoupper(mb_substr((string)preg_replace('/[^a-zA-Z\s]/', '', $childName), 0, 6));
        $productName = sprintf($tplName, $abbr);
        $price = $tplPrice ?? (rand(15, 95) * 50000);
        $sku   = $skuPrefix . strtoupper(substr(md5($childName . $idx), 0, 8));

        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $productFactory->create();
            $product->setAttributeSetId(4) // Default attribute set
                    ->setTypeId(Type::TYPE_SIMPLE)
                    ->setName($productName)
                    ->setSku($sku)
                    ->setPrice($price)
                    ->setSpecialPrice((int)($price * 0.85)) // 15% off
                    ->setDescription('<p>' . $tplDesc . '</p><p>Sản phẩm chính hãng, bảo hành 12 tháng.</p>')
                    ->setShortDescription($tplDesc)
                    ->setStatus(Status::STATUS_ENABLED)
                    ->setVisibility(Visibility::VISIBILITY_BOTH)
                    ->setWeight(rand(2, 15) / 10)
                    ->setTaxClassId(0)
                    ->setCategoryIds([$childCatId])
                    ->setWebsiteIds([1]);

            $product->save();

            // Set stock
            $stockItem = $stockRegistry->getStockItemBySku($sku);
            $stockItem->setQty(rand(20, 200))
                      ->setIsInStock(true)
                      ->setManageStock(true)
                      ->setMinQty(0)
                      ->setMinSaleQty(1)
                      ->setMaxSaleQty(10);
            $stockRegistry->updateStockItemBySku($sku, $stockItem);

            $productCount++;
            if ($productCount % 10 === 0) {
                echo "  🛒 Đã tạo {$productCount} sản phẩm...\n";
            }
        } catch (\Exception $e) {
            echo "  ⚠️ Lỗi sản phẩm '{$productName}': " . $e->getMessage() . "\n";
        }
    }
}

echo "\n✅ Hoàn thành!\n";
echo "📂 Đã tạo " . count($createdCategories) . " danh mục cha\n";
echo "📂 Đã tạo " . count($childCategoryMap) . " danh mục con\n";
echo "🛒 Đã tạo {$productCount} sản phẩm\n";
echo "\nChạy lại index và cache:\n";
echo "  php bin/magento indexer:reindex\n";
echo "  php bin/magento cache:flush\n";
