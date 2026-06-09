<?php
declare(strict_types=1);

namespace Vendor\Shipping\Service;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Vendor\Shipping\Model\Config;

class FreeShippingPolicy
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function isEligible(RateRequest $request, ?int $storeId = null): bool
    {
        if (!$this->config->isFreeShippingEnabled($storeId)) {
            return false;
        }

        $subtotal = (float)$request->getPackageValueWithDiscount();

        return $subtotal >= $this->config->getFreeShippingSubtotal($storeId);
    }
}
