<?php
declare(strict_types=1);

namespace Vendor\VNPay\Model\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Vendor\VNPay\Model\Config;

class SignatureServiceTest extends TestCase
{
    public function testSignAndVerifyCallbacks(): void
    {
        $service = new SignatureService($this->createConfig('PEAKGEAR', 'hash-secret'));

        $params = [
            'vnp_Amount' => 1000000,
            'vnp_Command' => 'pay',
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'PEAKGEAR',
            'vnp_TxnRef' => '100000001',
        ];

        $signature = $service->sign($params);
        $signedPayload = $params + ['vnp_SecureHash' => $signature];

        self::assertSame(hash_hmac('sha512', http_build_query($params, '', '&', PHP_QUERY_RFC3986), 'hash-secret'), $signature);
        self::assertTrue($service->verify($signedPayload));
        self::assertFalse($service->verify($params + ['vnp_SecureHash' => 'invalid']));
    }

    private function createConfig(string $tmnCode, string $hashSecret): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['payment/vnpay/tmn_code', 'store', null, $tmnCode],
            ['payment/vnpay/hash_secret', 'store', null, $hashSecret],
            ['payment/vnpay/vnp_url', 'store', null, 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'],
            ['payment/vnpay/vnp_return_url_path', 'store', null, 'vnpay/payment/return'],
            ['payment/vnpay/bank_code', 'store', null, ''],
        ]);
        $scopeConfig->method('isSetFlag')->willReturn(true);

        return new Config($scopeConfig);
    }
}
