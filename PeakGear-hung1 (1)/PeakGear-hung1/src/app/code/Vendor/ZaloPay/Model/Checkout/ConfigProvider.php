<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Vendor\ZaloPay\Model\Config;

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
                'zalopay' => [
                    'isConfigured' => $this->config->isGatewayConfigured(),
                ],
            ],
        ];
    }
}
