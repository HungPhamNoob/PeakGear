<?php
declare(strict_types=1);

namespace Vendor\VNPay\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Vendor\VNPay\Model\Config;

class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function getConfig(): array
    {
        return [
            'payment' => [
                'vnpay' => [
                    'isConfigured' => $this->config->isGatewayConfigured(),
                ],
            ],
        ];
    }
}
