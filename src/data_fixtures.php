<?php
/**
 * PeakGear Data Fixtures Script
 *
 * Tạo dữ liệu mẫu cho cửa hàng PeakGear:
 *   - 6 danh mục cha + 28 danh mục con (tổng 34 danh mục)
 *   - 280+ sản phẩm demo với giá, mô tả, tồn kho
 *
 * Cấu trúc danh mục đồng bộ với PeakGear_Catalog module và
 * PeakGear/climbing theme (CreateDefaultCategories data patch).
 *
 * Cách chạy trong Docker container:
 *   docker exec peakgear_php php /var/www/html/data_fixtures.php
 *
 * Sau khi chạy xong, cần reindex và flush cache:
 *   docker exec peakgear_php php /var/www/html/bin/magento indexer:reindex
 *   docker exec peakgear_php php /var/www/html/bin/magento cache:flush
 *
 * @category  PeakGear
 * @package   PeakGear_DataFixtures
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

/**
 * Khởi tạo area code ADMINHTML để có đủ quyền tạo category/product
 */
$appState = $objectManager->get(\Magento\Framework\App\State::class);
$appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

/** @var \Magento\Catalog\Model\CategoryFactory $categoryFactory */
$categoryFactory = $objectManager->get(\Magento\Catalog\Model\CategoryFactory::class);
/** @var \Magento\Catalog\Model\ProductFactory $productFactory */
$productFactory  = $objectManager->get(\Magento\Catalog\Model\ProductFactory::class);
/** @var StockRegistryInterface $stockRegistry */
$stockRegistry   = $objectManager->get(StockRegistryInterface::class);

/** ID danh mục gốc mặc định của Magento */
$rootCategoryId = 2;

// ============================================================================
// CẤU TRÚC DANH MỤC (6 danh mục cha + 28 danh mục con = 34 danh mục)
// Đồng bộ với PeakGear_Catalog::CreateDefaultCategories data patch
// ============================================================================
$categories = [
    // Danh mục 1: Giày Leo Núi
    'Giày Leo Núi' => [
        'Giày Trekking',
        'Giày Leo Vách',
        'Giày Approach',
        'Sandal Leo Núi',
    ],
    // Danh mục 2: Ba Lô
    'Ba Lô' => [
        'Ba Lô Day Trip (20-35L)',
        'Ba Lô Trekking (40-60L)',
        'Ba Lô Expedition (65L+)',
        'Túi Đeo Chéo',
    ],
    // Danh mục 3: Dây & Móc
    'Dây & Móc' => [
        'Dây Dynamic',
        'Dây Static',
        'Móc Carabiner',
        'Dây Đai An Toàn',
        'Thiết Bị Belay',
    ],
    // Danh mục 4: Lều & Trại
    'Lều & Trại' => [
        'Lều 1-2 Người',
        'Lều 3-4 Người',
        'Túi Ngủ',
        'Thảm & Đệm',
        'Bếp Dã Ngoại',
    ],
    // Danh mục 5: Áo Khoác
    'Áo Khoác' => [
        'Áo Gió Chống Nước',
        'Áo Giữ Nhiệt',
        'Áo Lông Vũ',
        'Áo Softshell',
    ],
    // Danh mục 6: Phụ Kiện
    'Phụ Kiện' => [
        'Đèn Pin & Đèn Đội Đầu',
        'La Bàn & GPS',
        'Bình Nước & Túi Nước',
        'Gậy Leo Núi',
        'Kính Mát',
        'Nón Bảo Hiểm',
    ],
];

// ============================================================================
// THƯƠNG HIỆU THEO NHÓM DANH MỤC
// Mỗi nhóm danh mục có danh sách thương hiệu phù hợp
// ============================================================================
$brandsByGroup = [
    'giay'     => ['La Sportiva', 'Scarpa', 'Salomon', 'Merrell', 'Lowa'],
    'ba lo'    => ['Osprey', 'Deuter', 'Gregory', 'Black Diamond', 'REI'],
    'day moc'  => ['Black Diamond', 'Petzl', 'Wild Country', 'Mammut', 'Edelrid'],
    'leu trai' => ['MSR', 'Big Agnes', 'Naturehike', 'Hilleberg', 'Mountain Hardwear'],
    'ao khoac' => ["Arc'teryx", 'Patagonia', 'Mammut', 'Rab', 'The North Face'],
    'phu kien' => ['Petzl', 'Garmin', 'Nalgene', 'Black Diamond', 'Julbo'],
];

// ============================================================================
// TEMPLATE SẢN PHẨM THEO DANH MỤC CON
// Mỗi danh mục con có 10 sản phẩm mẫu (tên, giá, mô tả)
// Template 'default' dùng cho các danh mục chưa có template riêng
// ============================================================================
$productTemplates = [
    // --- Giày Leo Núi ---
    'Giày Trekking' => [
        ['La Sportiva Ultra Raptor II MID GTX', 5200000, 'Giày trekking cổ trung chống nước, đế Vibram MegaGrip'],
        ['Salomon X Ultra 4 GTX Trekking', 4800000, 'Hệ thống ADV-C Chassis, chống nước GORE-TEX'],
        ['Merrell MQM 3 Mid GORE-TEX', 4500000, 'Đệm FloatPro, đế Vibram TC5+'],
        ['Lowa Renegade GTX Mid Trekking', 5600000, 'Khung PU Monowrap, lót GORE-TEX'],
        ['Scarpa Rush 2 Mid GTX', 5000000, 'Đế Presa MAG20, trọng lượng 480g'],
        ['Salomon Quest 4 GTX Hiking Boot', 5900000, 'Hệ thống 4D Advanced Chassis, đế ContraGrip MA'],
        ['La Sportiva TX5 GTX Approach', 4600000, 'Đế Vibram Mega-Grip, Impact Brake System'],
        ['Merrell Moab 3 Mid Waterproof', 3800000, 'Đệm Bellows Tongue, đế Vibram TC5+'],
        ['Lowa Innox Pro GTX Mid', 4200000, 'Trọng lượng 390g, DynaPU foam'],
        ['Scarpa Zodiac Plus GTX', 6200000, 'Full-grain leather, đế Vibram Drumlin'],
    ],
    'Giày Leo Vách' => [
        ['La Sportiva Solution Comp', 4800000, 'Đế Vibram XS Edge, dành cho bouldering'],
        ['Scarpa Drago LV Climbing Shoe', 4200000, 'Đế Vibram XS Grip2, thiết kế low volume'],
        ['La Sportiva Skwama Climbing', 4500000, 'S-Heel nhạy, đế neo Friction 3'],
        ['Scarpa Instinct VSR Rock Shoe', 4600000, 'Đế Vibram XS Edge, lining Microfiber'],
        ['La Sportiva Katana Lace', 3800000, 'Đế Vibram XS Edge 4mm, lacing system'],
        ['Scarpa Vapor V Climbing', 4100000, 'Bi-Tension active randing, đế Vibram XS Edge'],
        ['La Sportiva Miura VS Women', 4300000, 'Thiết kế cho nữ, đế Vibram XS Grip2'],
        ['Scarpa Maestro Mid Eco', 3500000, 'Chất liệu tái chế, đế Vibram XS'],
        ['La Sportiva Theory Climbing', 5100000, 'P3 System, No-edge technology'],
        ['Scarpa Chimera Climbing Shoe', 4700000, 'Vibram XS Grip2, Bi-Tension randing'],
    ],
    // --- Ba Lô ---
    'Ba Lô Day Trip (20-35L)' => [
        ['Osprey Talon 22 Day Hike Pack', 2500000, 'Dung tích 22L, AirSpeed back panel'],
        ['Deuter Speed Lite 30 Rucksack', 2800000, 'Dung tích 30L, nặng 680g, Speed hiking'],
        ['Gregory Zulu 30 Hiking Pack', 2900000, 'Dung tích 30L, Swiftcurrent suspension'],
        ['Black Diamond Bullet 16 Pack', 2100000, 'Dung tích 16L, tối giản, nặng 290g'],
        ['Osprey Daylite Plus Daypack', 1800000, 'Dung tích 20L, AirSpeed panel nhẹ'],
        ['Deuter Hiking 28 Day Pack', 2200000, 'Dung tích 28L, Aircomfort back system'],
        ['Salomon Trailblazer 30', 2400000, 'Dung tích 30L, nặng 550g'],
        ['Mammut Lithium 30 Hiking Pack', 3100000, 'Dung tích 30L, nặng 820g'],
        ['Osprey Exos 38 Ultralight Pack', 3200000, 'Dung tích 38L, nặng 958g, siêu nhẹ'],
        ['REI Co-op Flash 22 Pack', 1900000, 'Dung tích 22L, chống nước nhẹ'],
    ],
    'Ba Lô Trekking (40-60L)' => [
        ['Osprey Aether 65 Ba lô leo núi', 6800000, 'Dung tích 65L, nặng 2.3kg, chống nước IPX4'],
        ['Deuter Aircontact Core 60', 5500000, 'Dung tích 60L, nặng 2.1kg, Aircontact back'],
        ['Gregory Baltoro 75 Expedition', 7200000, 'Dung tích 75L, nặng 2.5kg, Response A3 hip belt'],
        ['Black Diamond Halo 40 Backpack', 4200000, 'Dung tích 40L, nặng 1.4kg, 3DF air mesh back'],
        ['Osprey Kestrel 48 Hiking Pack', 4800000, 'Dung tích 48L, nặng 1.5kg, IsoForm hipbelt'],
        ['Deuter Futura Vario 50', 5200000, 'Dung tích 50L, nặng 1.75kg, Vari-Quick back'],
        ['REI Co-op Traverse 70 Pack', 3900000, 'Dung tích 70L, nặng 2.2kg, A3 hipbelt'],
        ['Osprey Ariel 55 Women\'s Pack', 5100000, 'Dành cho nữ, dung tích 55L, nặng 2.0kg'],
        ['Gregory Deva 60 Women Backpack', 5600000, 'Dành cho nữ, dung tích 60L, GeoFit hipbelt'],
        ['Deuter Aviant Voyager 65', 6100000, 'Daypack tháo rời, dung tích 65L'],
    ],
    // --- MẪU MẶC ĐỊNH ---
    // Dùng cho các danh mục con chưa có template riêng
    // %s sẽ được thay bằng viết tắt tên danh mục
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

// ============================================================================
// BƯỚC 1: TẠO DANH MỤC (CATEGORIES)
// Tạo các danh mục cha và con theo cấu trúc đã định nghĩa
// ============================================================================
echo "=== PeakGear Data Fixtures ===\n";
echo "Cấu trúc: 6 danh mục cha + 28 danh mục con\n\n";

/** @var array $createdCategories Map: parent_name => parent_category_id */
$createdCategories = [];
/** @var array $childCategoryMap Map: child_name => child_category_id */
$childCategoryMap = [];

$i = 0;
foreach ($categories as $parentName => $children) {
    $i++;

    // --- Tạo danh mục cha ---
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
              ->setData('url_key', strtolower(str_replace(
                  [' ', '&', ',', '/'],
                  ['-', '', '', '-'],
                  iconv('UTF-8', 'ASCII//TRANSLIT', $parentName)
              )) . '-' . $i);

    try {
        $parentCat->save();
        $createdCategories[$parentName] = $parentCat->getId();
        echo "✅ Tạo danh mục cha: [{$parentCat->getId()}] {$parentName}\n";

        // --- Tạo các danh mục con ---
        $j = 0;
        foreach ($children as $childName) {
            $j++;
            /** @var \Magento\Catalog\Model\Category $childCat */
            $childCat = $categoryFactory->create();
            $childCat->setName($childName)
                     ->setIsActive(true)
                     ->setParentId($parentCat->getId())
                     ->setPath('1/' . $rootCategoryId . '/' . $parentCat->getId())
                     ->setIncludeInMenu(true)
                     ->setDisplayMode('PRODUCTS')
                     ->setData('description', 'Danh mục ' . $childName)
                     ->setData('url_key', strtolower(str_replace(
                         [' ', '&', ',', '/'],
                         ['-', '', '', '-'],
                         iconv('UTF-8', 'ASCII//TRANSLIT', $childName)
                     )) . '-' . $i . $j);

            $childCat->save();
            $childCategoryMap[$childName] = $childCat->getId();
            echo "  📂 Tạo danh mục con: [{$childCat->getId()}] {$childName}\n";
        }
    } catch (\Exception $e) {
        echo "❌ Lỗi tạo danh mục: {$e->getMessage()}\n";
    }
}

// ============================================================================
// BƯỚC 2: TẠO SẢN PHẨM (PRODUCTS)
// Mỗi danh mục con có 10 sản phẩm từ template tương ứng
// Sản phẩm có giá gốc, giá khuyến mãi (-15%), tồn kho ngẫu nhiên
// ============================================================================
echo "\n=== Tạo sản phẩm demo ===\n";

/** @var int $productCount Bộ đếm tổng sản phẩm đã tạo */
$productCount = 0;
/** @var string $skuPrefix Tiền tố SKU cho tất cả sản phẩm PeakGear */
$skuPrefix = 'PG-';

foreach ($childCategoryMap as $childName => $childCatId) {
    // Chọn template sản phẩm: dùng template riêng nếu có, không thì dùng 'default'
    $templates = $productTemplates[$childName] ?? $productTemplates['default'];

    foreach ($templates as $idx => $tpl) {
        [$tplName, $tplPrice, $tplDesc] = $tpl;

        // Tạo viết tắt tên danh mục (6 ký tự đầu, chỉ chữ cái) cho template default
        $abbr = mb_strtoupper(mb_substr(
            (string)preg_replace('/[^a-zA-Z\s]/', '', $childName),
            0, 6
        ));
        $productName = sprintf($tplName, $abbr);

        // Giá: dùng giá template nếu có, nếu không thì random 750K-4.75M VND
        $price = $tplPrice ?? (rand(15, 95) * 50000);

        // SKU: tiền tố PG- + 8 ký tự hash từ tên danh mục + index
        $sku = $skuPrefix . strtoupper(substr(md5($childName . $idx), 0, 8));

        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $productFactory->create();
            $product->setAttributeSetId(4) // Bộ thuộc tính mặc định (Default)
                    ->setTypeId(Type::TYPE_SIMPLE)
                    ->setName($productName)
                    ->setSku($sku)
                    ->setPrice($price)
                    ->setSpecialPrice((int)($price * 0.85)) // Giảm giá 15%
                    ->setDescription(
                        '<p>' . $tplDesc . '</p>'
                        . '<p>Sản phẩm chính hãng, bảo hành 12 tháng.</p>'
                    )
                    ->setShortDescription($tplDesc)
                    ->setStatus(Status::STATUS_ENABLED)
                    ->setVisibility(Visibility::VISIBILITY_BOTH)
                    ->setWeight(rand(2, 15) / 10) // Trọng lượng 0.2-1.5 kg
                    ->setTaxClassId(0)
                    ->setCategoryIds([$childCatId])
                    ->setWebsiteIds([1]);

            $product->save();

            // --- Cập nhật tồn kho ---
            $stockItem = $stockRegistry->getStockItemBySku($sku);
            $stockItem->setQty(rand(20, 200))     // Số lượng 20-200
                      ->setIsInStock(true)          // Còn hàng
                      ->setManageStock(true)         // Quản lý tồn kho
                      ->setMinQty(0)                 // Tồn kho tối thiểu
                      ->setMinSaleQty(1)             // Mua tối thiểu 1
                      ->setMaxSaleQty(10);           // Mua tối đa 10
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

// ============================================================================
// KẾT QUẢ
// ============================================================================
echo "\n✅ Hoàn thành!\n";
echo "📂 Đã tạo " . count($createdCategories) . " danh mục cha\n";
echo "📂 Đã tạo " . count($childCategoryMap) . " danh mục con\n";
echo "🛒 Đã tạo {$productCount} sản phẩm\n";
echo "\nChạy lại index và cache:\n";
echo "  php bin/magento indexer:reindex\n";
echo "  php bin/magento cache:flush\n";
