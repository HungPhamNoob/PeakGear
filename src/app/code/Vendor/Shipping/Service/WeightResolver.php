<?php
declare(strict_types=1);

namespace Vendor\Shipping\Service;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Vendor\Shipping\Model\Config;

class WeightResolver
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function resolveGram(RateRequest $request, ?int $storeId = null): int
    {
        $weight = (float)$request->getPackageWeight();
        if ($weight <= 0) {
            return $this->config->getDefaultWeightGram($storeId);
        }

        $weightGram = $this->config->getWeightUnit($storeId) === 'g'
            ? $weight
            : $weight * 1000;

        return max(1, (int)round($weightGram));
    }
}
