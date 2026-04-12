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
        string $appUser,
        int $amount,
        int $appTime,
        string $embedData,
        string $items
    ): string {
        $payload = implode('|', [
            $this->config->getAppId(),
            $appTransId,
            $appUser,
            (string)$amount,
            (string)$appTime,
            $embedData,
            $items,
        ]);

        return hash_hmac('sha256', $payload, $this->config->getKey1());
    }

    public function buildQueryMac(string $appTransId): string
    {
        $payload = implode('|', [
            $this->config->getAppId(),
            $appTransId,
            $this->config->getKey1(),
        ]);

        return hash_hmac('sha256', $payload, $this->config->getKey1());
    }

    public function verifyCallback(array $payload): bool
    {
        $expectedMac = hash_hmac('sha256', (string)($payload['data'] ?? ''), $this->config->getKey2());

        return hash_equals($expectedMac, (string)($payload['mac'] ?? ''));
    }
}
