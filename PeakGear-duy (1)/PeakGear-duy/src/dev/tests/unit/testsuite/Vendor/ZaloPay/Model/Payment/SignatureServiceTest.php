<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Model\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Vendor\ZaloPay\Model\Config;

class SignatureServiceTest extends TestCase
{
    public function testBuildCreateOrderMacUsesConfiguredSecrets(): void
    {
        $service = new SignatureService($this->createConfig('2553', 'key1-secret', 'key2-secret'));

        $mac = $service->buildCreateOrderMac(
            '240101_100000001',
            150000,
            1700000000000,
            '{"redirecturl":"https://example.com/success"}',
            '[]'
        );

        self::assertSame(
            hash_hmac(
                'sha256',
                '2553|240101_100000001|PeakGearUser|150000|1700000000000|{"redirecturl":"https://example.com/success"}|[]',
                'key1-secret'
            ),
            $mac
        );
    }

    public function testVerifyCallbackUsesKeyTwo(): void
    {
        $service = new SignatureService($this->createConfig('2553', 'key1-secret', 'key2-secret'));
        $payload = ['data' => '{"app_trans_id":"240101_100000001"}'];
        $payload['mac'] = hash_hmac('sha256', $payload['data'], 'key2-secret');

        self::assertTrue($service->verifyCallback($payload));
        self::assertFalse($service->verifyCallback(['data' => $payload['data'], 'mac' => 'invalid']));
    }

    private function createConfig(string $appId, string $key1, string $key2): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['payment/zalopay/app_id', 'store', null, $appId],
            ['payment/zalopay/key1', 'store', null, $key1],
            ['payment/zalopay/key2', 'store', null, $key2],
            ['payment/zalopay/api_url', 'store', null, 'https://sb-openapi.zalopay.vn/v2/create'],
        ]);
        $scopeConfig->method('isSetFlag')->willReturn(true);

        return new Config($scopeConfig);
    }
}
