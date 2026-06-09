<?php
declare(strict_types=1);

namespace Vendor\Shipping\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SeedVietnamRegions implements DataPatchInterface
{
    private const COUNTRY_ID = 'VN';
    private const LOCALE = 'vi_VN';

    private const REGIONS = [
        ['code' => 'VN-AG', 'name' => 'An Giang'],
        ['code' => 'VN-BRVT', 'name' => 'Bà Rịa - Vũng Tàu'],
        ['code' => 'VN-BG', 'name' => 'Bắc Giang'],
        ['code' => 'VN-BK', 'name' => 'Bắc Kạn'],
        ['code' => 'VN-BL', 'name' => 'Bạc Liêu'],
        ['code' => 'VN-BN', 'name' => 'Bắc Ninh'],
        ['code' => 'VN-BTR', 'name' => 'Bến Tre'],
        ['code' => 'VN-BD', 'name' => 'Bình Định'],
        ['code' => 'VN-BDU', 'name' => 'Bình Dương'],
        ['code' => 'VN-BP', 'name' => 'Bình Phước'],
        ['code' => 'VN-BTH', 'name' => 'Bình Thuận'],
        ['code' => 'VN-CM', 'name' => 'Cà Mau'],
        ['code' => 'VN-CT', 'name' => 'Cần Thơ'],
        ['code' => 'VN-CB', 'name' => 'Cao Bằng'],
        ['code' => 'VN-DN', 'name' => 'Đà Nẵng'],
        ['code' => 'VN-DL', 'name' => 'Đắk Lắk'],
        ['code' => 'VN-DNO', 'name' => 'Đắk Nông'],
        ['code' => 'VN-DB', 'name' => 'Điện Biên'],
        ['code' => 'VN-DNA', 'name' => 'Đồng Nai'],
        ['code' => 'VN-DT', 'name' => 'Đồng Tháp'],
        ['code' => 'VN-GL', 'name' => 'Gia Lai'],
        ['code' => 'VN-HG', 'name' => 'Hà Giang'],
        ['code' => 'VN-HNA', 'name' => 'Hà Nam'],
        ['code' => 'VN-HN', 'name' => 'Hà Nội'],
        ['code' => 'VN-HT', 'name' => 'Hà Tĩnh'],
        ['code' => 'VN-HD', 'name' => 'Hải Dương'],
        ['code' => 'VN-HP', 'name' => 'Hải Phòng'],
        ['code' => 'VN-HGI', 'name' => 'Hậu Giang'],
        ['code' => 'VN-HB', 'name' => 'Hòa Bình'],
        ['code' => 'VN-HY', 'name' => 'Hưng Yên'],
        ['code' => 'VN-KH', 'name' => 'Khánh Hòa'],
        ['code' => 'VN-KG', 'name' => 'Kiên Giang'],
        ['code' => 'VN-KT', 'name' => 'Kon Tum'],
        ['code' => 'VN-LC', 'name' => 'Lai Châu'],
        ['code' => 'VN-LD', 'name' => 'Lâm Đồng'],
        ['code' => 'VN-LS', 'name' => 'Lạng Sơn'],
        ['code' => 'VN-LAO', 'name' => 'Lào Cai'],
        ['code' => 'VN-LA', 'name' => 'Long An'],
        ['code' => 'VN-ND', 'name' => 'Nam Định'],
        ['code' => 'VN-NA', 'name' => 'Nghệ An'],
        ['code' => 'VN-NB', 'name' => 'Ninh Bình'],
        ['code' => 'VN-NT', 'name' => 'Ninh Thuận'],
        ['code' => 'VN-PT', 'name' => 'Phú Thọ'],
        ['code' => 'VN-PY', 'name' => 'Phú Yên'],
        ['code' => 'VN-QB', 'name' => 'Quảng Bình'],
        ['code' => 'VN-QN', 'name' => 'Quảng Nam'],
        ['code' => 'VN-QNG', 'name' => 'Quảng Ngãi'],
        ['code' => 'VN-QNI', 'name' => 'Quảng Ninh'],
        ['code' => 'VN-QT', 'name' => 'Quảng Trị'],
        ['code' => 'VN-ST', 'name' => 'Sóc Trăng'],
        ['code' => 'VN-SL', 'name' => 'Sơn La'],
        ['code' => 'VN-TN', 'name' => 'Tây Ninh'],
        ['code' => 'VN-TB', 'name' => 'Thái Bình'],
        ['code' => 'VN-TNG', 'name' => 'Thái Nguyên'],
        ['code' => 'VN-TH', 'name' => 'Thanh Hóa'],
        ['code' => 'VN-TTH', 'name' => 'Thừa Thiên Huế'],
        ['code' => 'VN-TG', 'name' => 'Tiền Giang'],
        ['code' => 'VN-HCM', 'name' => 'TP. Hồ Chí Minh'],
        ['code' => 'VN-TV', 'name' => 'Trà Vinh'],
        ['code' => 'VN-TQ', 'name' => 'Tuyên Quang'],
        ['code' => 'VN-VL', 'name' => 'Vĩnh Long'],
        ['code' => 'VN-VP', 'name' => 'Vĩnh Phúc'],
        ['code' => 'VN-YB', 'name' => 'Yên Bái'],
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $regionTable = $this->moduleDataSetup->getTable('directory_country_region');
        $regionNameTable = $this->moduleDataSetup->getTable('directory_country_region_name');

        foreach (self::REGIONS as $region) {
            $regionId = $this->resolveRegionId($regionTable, $region['code'], $region['name']);

            if ($regionId === null) {
                $connection->insert($regionTable, [
                    'country_id' => self::COUNTRY_ID,
                    'code' => $region['code'],
                    'default_name' => $region['name'],
                ]);
                $regionId = (int)$connection->lastInsertId($regionTable);
            } else {
                $connection->update(
                    $regionTable,
                    [
                        'code' => $region['code'],
                        'default_name' => $region['name'],
                    ],
                    ['region_id = ?' => $regionId]
                );
            }

            $connection->insertOnDuplicate(
                $regionNameTable,
                [
                    'locale' => self::LOCALE,
                    'region_id' => $regionId,
                    'name' => $region['name'],
                ],
                ['name']
            );
        }

        $connection->endSetup();

        return $this;
    }

    private function resolveRegionId(string $regionTable, string $code, string $name): ?int
    {
        $connection = $this->moduleDataSetup->getConnection();
        $identityWhere = sprintf(
            '(code = %s OR default_name = %s)',
            $connection->quote($code),
            $connection->quote($name)
        );
        $select = $connection->select()
            ->from($regionTable, ['region_id'])
            ->where('country_id = ?', self::COUNTRY_ID)
            ->where($identityWhere)
            ->limit(1);

        $regionId = $connection->fetchOne($select);

        return $regionId !== false ? (int)$regionId : null;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
