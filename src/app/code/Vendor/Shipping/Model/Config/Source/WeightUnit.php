<?php
declare(strict_types=1);

namespace Vendor\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WeightUnit implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'kg', 'label' => __('Kilogram (kg)')],
            ['value' => 'g', 'label' => __('Gram (g)')],
        ];
    }
}
