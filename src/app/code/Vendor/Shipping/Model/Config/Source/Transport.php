<?php
declare(strict_types=1);

namespace Vendor\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Transport implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'road', 'label' => __('Road')],
            ['value' => 'fly', 'label' => __('Air')],
        ];
    }
}
