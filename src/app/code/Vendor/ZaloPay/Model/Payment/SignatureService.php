<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Model\Payment;

use Vendor\ZaloPay\Model\Config;

/**
 * Encapsulates ZaloPay request and callback signature logic.
 */
class SignatureService
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function buildCreateOrderMac(
        string $appTransId,
        int $amount,
        int $appTime,
        string $embedData,
        string $items
    ): string {
        $payload = implode('|', [
            $this->config->getAppId(),
            $appTransId,
            'PeakGearUser',
            (string)$amount,
            (string)$appTime,
            $embedData,
            $items,
        ]);

        return hash_hmac('sha256', $payload, $this->config->getKey1());
    }

    public function verifyCallback(array $payload): bool
    {
        $expectedMac = hash_hmac('sha256', (string)($payload['data'] ?? ''), $this->config->getKey2());

        return hash_equals($expectedMac, (string)($payload['mac'] ?? ''));
    }
}
