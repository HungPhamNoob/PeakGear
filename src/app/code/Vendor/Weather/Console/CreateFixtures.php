<?php
declare(strict_types=1);

namespace Vendor\Weather\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Type;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
/**
 * Lớp CreateFixtures dùng để tạo dữ liệu mẫu (Fixtures) cho dự án PeakGear.
 * Cung cấp danh mục (Categories) và sản phẩm (Products) phục vụ kiểm thử hệ thống.
 * 
 * @package Vendor\Weather\Console
 */
class CreateFixtures extends Command
{
    private const ROOT_CATEGORY_ID = 2;
    /**
     * @var array Danh sách các danh mục sản phẩm đồ dã ngoại (outdoor) và con của chúng.
     */
    private array $categories = [
        'Ba lô & Túi xách Outdoor' => [
            'Ba lô leo núi (trên 40L)',
            'Ba lô trekking (20-40L)',
            'Túi đeo chéo & Waist pack',
        ],
        'Lều & Trại cắm trại' => [
            'Lều solo và đôi',
            'Lều gia đình 3-4 người',
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

    /**
     * @var array Danh sách các thương hiệu sản phẩm nổi tiếng.
     */    private array $brands = [
        'Osprey', 'Deuter', 'Gregory', "Arc'teryx", 'Patagonia',
        'Mammut', 'Rab', 'The North Face', 'MSR', 'Petzl',
        'Black Diamond', 'Salomon', 'Lowa', 'Merrell', 'Garmin',
        'Big Agnes', 'Naturehike', 'Sea to Summit', 'Jetboil', 'GSI Outdoors',
    ];

    /**
     * Khởi tạo command CreateFixtures.
     * Inject các Dependency cần thiết để tạo Category và Product.
     *
     * @param CategoryFactory $categoryFactory Factory tạo mới Danh mục.
     * @param ProductFactory $productFactory Factory tạo mới Sản phẩm.
     * @param StockRegistryInterface $stockRegistry Giao diện quản lý tồn kho.
     * @param State $appState Trạng thái ứng dụng Magento (Set AreaCode).
     * @param string|null $name Tên lệnh CLI.
     *
     * Lưu ý quan trọng: CategoryFactory và ProductFactory được tạo tự động bởi Magento DI.
     * Vui lòng chạy `php bin/magento setup:di:compile` nếu bị lỗi thiếu Factory.
     */
    public function __construct(
        private CategoryFactory        $categoryFactory,
        private ProductFactory         $productFactory,
        private StockRegistryInterface $stockRegistry,
        private State                  $appState,
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Cấu hình lệnh console trước khi thực thi.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('peakgear:fixtures:create')
             ->setDescription('Tạo dữ liệu mẫu danh mục và sản phẩm cho PeakGear (44 danh mục, 330 sản phẩm)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Required: set area code for Magento models
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // already set, continue
        }

        $output->writeln('<info>=== PeakGear Data Fixtures ===</info>');

        $childCategoryMap = [];
        $parentCount = 0;

        foreach ($this->categories as $parentName => $children) {
            $parentCount++;
            try {
                $parentCat = $this->categoryFactory->create();
                $parentCat->setName($parentName)
                          ->setIsActive(true)
                          ->setParentId(self::ROOT_CATEGORY_ID)
                          ->setPath('1/' . self::ROOT_CATEGORY_ID)
                          ->setIncludeInMenu(true)
                          ->setDisplayMode('PRODUCTS')
                          ->setData('description', 'Danh mục ' . $parentName . ' - PeakGear outdoor shop')
                          ->setData('url_key', $this->makeUrlKey($parentName) . '-pg' . $parentCount);
                $parentCat->save();

                $output->writeln("✅ Tạo danh mục cha #{$parentCat->getId()}: {$parentName}");

                $j = 0;
                foreach ($children as $childName) {
                    $j++;
                    $childCat = $this->categoryFactory->create();
                    $childCat->setName($childName)
                             ->setIsActive(true)
                             ->setParentId($parentCat->getId())
                             ->setPath('1/' . self::ROOT_CATEGORY_ID . '/' . $parentCat->getId())
                             ->setIncludeInMenu(true)
                             ->setDisplayMode('PRODUCTS')
                             ->setData('url_key', $this->makeUrlKey($childName) . '-c' . $parentCount . $j);
                    $childCat->save();
                    $childCategoryMap[$childName] = $childCat->getId();
                    $output->writeln("  📂 #{$childCat->getId()}: {$childName}");
                }
            } catch (\Exception $e) {
                $output->writeln("<error>Lỗi danh mục {$parentName}: {$e->getMessage()}</error>");
            }
        }

        $output->writeln("\n<info>=== Tạo sản phẩm demo ===</info>");
        $productCount = 0;

        // Vòng lặp 2: Tạo sản phẩm đối với mỗi Danh mục con
        foreach ($childCategoryMap as $childName => $childCatId) {
            for ($i = 1; $i <= 10; $i++) {
                $brand = $this->brands[($productCount) % count($this->brands)];
                $productName = $brand . ' ' . $childName . ' - Mẫu ' . $i;
                $sku = 'PG-' . strtoupper(substr(md5($childName . $i), 0, 8));
                $price = rand(3, 50) * 100000; // 300k - 5M VND

                try {
                    /** @var \Magento\Catalog\Model\Product $product */
                    $product = $this->productFactory->create();
                    $product->setAttributeSetId(4) // Attribute set mặc định ID = 4
                            ->setTypeId(Type::TYPE_SIMPLE)
                            ->setName($productName)
                            ->setSku($sku)
                            ->setPrice($price)
                            ->setSpecialPrice((int)($price * 0.85)) // Khuyến mãi giảm 15%
                            ->setDescription('<p>Sản phẩm outdoor chất lượng cao mang thương hiệu <strong>' . $brand . '</strong>.</p><p>Phù hợp cho các hoạt động trekking, leo núi và dã ngoại tại tự nhiên Việt Nam.</p><ul><li>Chất liệu: Vật liệu chống chịu thời tiết cao cấp</li><li>Trọng lượng: ' . (rand(2,20)/10) . ' kg</li><li>Bảo hành chính hãng: 12 tháng</li></ul>')
                            ->setShortDescription($brand . ' ' . $childName . ' - đạt chuẩn xuất khẩu chất lượng cao, phục vụ chuyên dụng cho tín đồ trekking.')
                            ->setStatus(Status::STATUS_ENABLED)
                            ->setVisibility(Visibility::VISIBILITY_BOTH) // Hiển thị trên cả Catalog và Search
                            ->setWeight(rand(2, 15) / 10)
                            ->setTaxClassId(0)
                            ->setCategoryIds([$childCatId])
                            ->setWebsiteIds([1]); // Liên kết với Website chính (Mặc định ID = 1)

                    $product->save();

                    // Thiết lập quản lý tồn kho (Stock Item)
                    $stockItem = $this->stockRegistry->getStockItemBySku($sku);
                    $stockItem->setQty(rand(20, 200)) // Số lượng trong kho
                              ->setIsInStock(true) // Trạng thái Còn hàng
                              ->setManageStock(true) // Bật Quản lý kho
                              ->setMinQty(0)
                              ->setMinSaleQty(1)
                              ->setMaxSaleQty(10); // Mỗi lần mua tối đa 10 sản phẩm
                    $this->stockRegistry->updateStockItemBySku($sku, $stockItem);

                    $productCount++;
                } catch (\Exception $e) {
                    $output->writeln("<comment>  ⚠️ Lỗi phát sinh tại SKU {$sku}: {$e->getMessage()}</comment>");
                }
            }

            $output->writeln("  🛒 Danh mục [{$childCatId}] {$childName}: Đã thêm 10 sản phẩm");
        }

        $output->writeln("\n<info>✅ Tiến trình chạy Fixtures đã hoàn thành!</info>");
        $output->writeln("<info>📂 Số danh mục cha đã tạo: " . count($this->categories) . "</info>");
        $output->writeln("<info>📂 Số danh mục con phát sinh: " . count($childCategoryMap) . "</info>");
        $output->writeln("<info>🛒 Tổng số lượng sản phẩm được tạo: {$productCount}</info>");
        $output->writeln("\nVui lòng tiếp tục chạy các lệnh sau để cập nhật hệ thống hiển thị:");
        $output->writeln("  php bin/magento indexer:reindex");
        $output->writeln("  php bin/magento cache:flush");

        return Command::SUCCESS;
    }

    /**
     * Tạo chuỗi URL Key từ tên Danh Mục / Sản Phẩm.
     * Chuyển đổi các ký tự có dấu, khoảng trắng thành chuỗi thân thiện với SEO.
     *
     * @param string $name Tên ban đầu của đối tượng.
     * @return string Chuỗi URL key.
     */
    private function makeUrlKey(string $name): string
    {
        // Chuyển đổi ký tự tiếng Việt có dấu thành không dấu
        $vietnameseChars = [
            'á','à','ả','ã','ạ','ă','ắ','ằ','ẳ','ẵ','ặ','â','ấ','ầ','ẩ','ẫ','ậ',
            'đ',
            'é','è','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ',
            'í','ì','ỉ','ĩ','ị',
            'ó','ò','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ',
            'ú','ù','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự',
            'ý','ỳ','ỷ','ỹ','ỵ',
            'Á','À','Ả','Ã','Ạ','Ă','Ắ','Ằ','Ẳ','Ẵ','Ặ','Â','Ấ','Ầ','Ẩ','Ẫ','Ậ',
            'Đ',
            'É','È','Ẻ','Ẽ','Ẹ','Ê','Ế','Ề','Ể','Ễ','Ệ',
            'Í','Ì','Ỉ','Ĩ','Ị',
            'Ó','Ò','Ỏ','Õ','Ọ','Ô','Ố','Ồ','Ổ','Ỗ','Ộ','Ơ','Ớ','Ờ','Ở','Ỡ','Ợ',
            'Ú','Ù','Ủ','Ũ','Ụ','Ư','Ứ','Ừ','Ử','Ữ','Ự',
            'Ý','Ỳ','Ỷ','Ỹ','Ỵ'
        ];
        $asciiChars = [
            'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
            'd',
            'e','e','e','e','e','e','e','e','e','e','e',
            'i','i','i','i','i',
            'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
            'u','u','u','u','u','u','u','u','u','u','u',
            'y','y','y','y','y',
            'A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A',
            'D',
            'E','E','E','E','E','E','E','E','E','E','E',
            'I','I','I','I','I',
            'O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O',
            'U','U','U','U','U','U','U','U','U','U','U',
            'Y','Y','Y','Y','Y'
        ];
        
        $name = str_replace($vietnameseChars, $asciiChars, $name);
        $name = str_replace(['&', ',', '/', '\\', '(', ')', '.'], '', strtolower($name));
        $name = preg_replace('/\s+/', '-', trim($name));
        $name = preg_replace('/-+/', '-', $name);
        return substr($name, 0, 60);
    }
}
