<?php
declare(strict_types=1);

namespace PeakGear\CartRoute\Plugin\OfflineShipping;

use Magento\OfflineShipping\Model\Carrier\Flatrate;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method as RateMethod;
use Magento\Shipping\Model\Rate\Result as RateResult;

class FlatrateRegionPricePlugin
{
    private const NORTH_PRICE = 100000;
    private const CENTRAL_PRICE = 500000;
    private const SOUTH_PRICE = 1000000;

    private const NORTH_CITIES = [
        'ha noi', 'hai phong', 'quang ninh', 'bac ninh', 'bac giang', 'ha nam',
        'hai duong', 'hung yen', 'nam dinh', 'ninh binh', 'thai binh', 'vinh phuc',
        'phu tho', 'thai nguyen', 'tuyen quang', 'ha giang', 'cao bang', 'bac kan',
        'lang son', 'lao cai', 'yen bai', 'dien bien', 'lai chau', 'son la', 'hoa binh'
    ];

    private const CENTRAL_CITIES = [
        'thanh hoa', 'nghe an', 'ha tinh', 'quang binh', 'quang tri', 'thua thien hue',
        'hue', 'da nang', 'quang nam', 'quang ngai', 'binh dinh', 'phu yen',
        'khanh hoa', 'ninh thuan', 'binh thuan', 'kon tum', 'gia lai', 'dak lak',
        'dak nong', 'lam dong'
    ];

    private const SOUTH_CITIES = [
        'ho chi minh', 'can tho', 'an giang', 'ba ria vung tau', 'vung tau',
        'bac lieu', 'ben tre', 'binh duong', 'binh phuoc', 'ca mau', 'dong thap',
        'dong nai', 'hau giang', 'kien giang', 'long an', 'soc trang',
        'tay ninh', 'tien giang', 'tra vinh', 'vinh long'
    ];

    public function afterCollectRates(Flatrate $subject, $result, RateRequest $request)
    {
        if (!$result instanceof RateResult) {
            return $result;
        }

        $destination = $this->resolveDestinationLocation($request);
        $shippingPrice = $this->resolvePriceByCity($destination);

        foreach ($result->getAllRates() as $rate) {
            if (!$rate instanceof RateMethod || (string)$rate->getCarrier() !== 'flatrate') {
                continue;
            }

            $rate->setPrice($shippingPrice);
            $rate->setCost($shippingPrice);
            $rate->setMethodTitle((string)__('Giao hàng theo khu vực'));
            $rate->setCarrierTitle((string)__('Vận chuyển nội địa'));
        }

        return $result;
    }

    private function resolveDestinationLocation(RateRequest $request): string
    {
        $candidates = [
            (string)$request->getDestCity(),
            (string)$request->getDestRegion(),
            (string)$request->getDestRegionCode(),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalize($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function resolvePriceByCity(string $city): int
    {
        if (in_array($city, self::NORTH_CITIES, true)) {
            return self::NORTH_PRICE;
        }

        if (in_array($city, self::SOUTH_CITIES, true)) {
            return self::SOUTH_PRICE;
        }

        if (in_array($city, self::CENTRAL_CITIES, true)) {
            return self::CENTRAL_PRICE;
        }

        return self::CENTRAL_PRICE;
    }

    private function normalize(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        $normalized = preg_replace('/^(thanh\s*pho|tp\.?|tinh)\s+/u', '', $normalized) ?? $normalized;

        $normalized = strtr($normalized, [
            'a' => 'a',
            'à' => 'a',
            'á' => 'a',
            'ạ' => 'a',
            'ả' => 'a',
            'ã' => 'a',
            'ă' => 'a',
            'ằ' => 'a',
            'ắ' => 'a',
            'ặ' => 'a',
            'ẳ' => 'a',
            'ẵ' => 'a',
            'â' => 'a',
            'ầ' => 'a',
            'ấ' => 'a',
            'ậ' => 'a',
            'ẩ' => 'a',
            'ẫ' => 'a',
            'è' => 'e',
            'é' => 'e',
            'ẹ' => 'e',
            'ẻ' => 'e',
            'ẽ' => 'e',
            'ê' => 'e',
            'ề' => 'e',
            'ế' => 'e',
            'ệ' => 'e',
            'ể' => 'e',
            'ễ' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'ị' => 'i',
            'ỉ' => 'i',
            'ĩ' => 'i',
            'ò' => 'o',
            'ó' => 'o',
            'ọ' => 'o',
            'ỏ' => 'o',
            'õ' => 'o',
            'ô' => 'o',
            'ồ' => 'o',
            'ố' => 'o',
            'ộ' => 'o',
            'ổ' => 'o',
            'ỗ' => 'o',
            'ơ' => 'o',
            'ờ' => 'o',
            'ớ' => 'o',
            'ợ' => 'o',
            'ở' => 'o',
            'ỡ' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'ụ' => 'u',
            'ủ' => 'u',
            'ũ' => 'u',
            'ư' => 'u',
            'ừ' => 'u',
            'ứ' => 'u',
            'ự' => 'u',
            'ử' => 'u',
            'ữ' => 'u',
            'ỳ' => 'y',
            'ý' => 'y',
            'ỵ' => 'y',
            'ỷ' => 'y',
            'ỹ' => 'y',
            'đ' => 'd',
            '.' => ' '
        ]);

        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? $normalized;

        $normalized = trim((string)(preg_replace('/\s+/', ' ', $normalized) ?? $normalized));

        $aliases = [
            'hcm' => 'ho chi minh',
            'tphcm' => 'ho chi minh',
            'ho chi minh city' => 'ho chi minh',
            'ba ria vung tau' => 'vung tau',
            'thua thien hue' => 'hue',
        ];

        return $aliases[$normalized] ?? $normalized;
    }
}
