<?php
declare(strict_types=1);

namespace Vendor\VNPay\Model\Payment;

use Vendor\VNPay\Model\Config;

/**
 * Builds the redirect URL query payload sent to VNPay.
 */
class RedirectRequestBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly SignatureService $signatureService
    ) {
    }

    public function buildUrl(
        string $orderId,
        int $amount,
        string $orderInfo,
        string $returnUrl,
        string $ipAddr,
        string $locale = 'vn'
    ): string {
        $timezone = new \DateTimeZone('Asia/Ho_Chi_Minh');
        $now = new \DateTimeImmutable('now', $timezone);

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->config->getTmnCode(),
            'vnp_Locale' => $locale,
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $orderId,
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => 'other',
            'vnp_Amount' => $amount * 100,
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_IpAddr' => $ipAddr,
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_ExpireDate' => $now->modify('+30 minutes')->format('YmdHis'),
        ];

        $bankCode = $this->config->getBankCode();
        if ($bankCode !== '') {
            $params['vnp_BankCode'] = $bankCode;
        }

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC1738);
        $secureHash = $this->signatureService->sign($params);

        return $this->config->getGatewayUrl()
            . '?' . $query
            . '&vnp_SecureHashType=SHA512'
            . '&vnp_SecureHash=' . $secureHash;
    }
}
