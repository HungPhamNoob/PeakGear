<?php
declare(strict_types=1);

namespace Vendor\VNPay\Model\Payment;

use Vendor\VNPay\Model\Config;

/**
 * Encapsulates VNPay secure-hash generation and verification.
 */
class SignatureService
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, scalar> $params
     */
    public function sign(array $params): string
    {
        $params = array_filter($params, static function ($value): bool {
            return $value !== null && $value !== '';
        });

        ksort($params);
        // VNPay sample integration uses urlencode (RFC1738), not RFC3986.
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC1738);

        return hash_hmac('sha512', $query, $this->config->getHashSecret());
    }

    /**
     * @param array<string, scalar> $params
     */
    public function verify(array $params): bool
    {
        $providedHash = strtolower((string)($params['vnp_SecureHash'] ?? ''));
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);

        return hash_equals(strtolower($this->sign($params)), $providedHash);
    }
}
