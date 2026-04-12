<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Model\Payment;

use Vendor\ZaloPay\Model\Config;

/**
 * Builds the outbound request payload expected by ZaloPay's create-order API.
 */
class CreateOrderRequestBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly SignatureService $signatureService
    ) {
    }

    /**
     * @return array{post_data:array<string, int|string>, app_trans_id:string}
     */
    public function build(
        string $orderId,
        int $amount,
        string $description,
        string $callbackUrl,
        string $redirectUrl
    ): array {
        $vnNow = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));
        $suffix = strtoupper(substr(hash('sha256', $orderId . '|' . (string)microtime(true)), 0, 6));
        $appTransId = $vnNow->format('ymd') . '_' . $orderId . '-' . $suffix;
        $appUser = $this->config->getAppUser();
        $appTime = (int)round(microtime(true) * 1000);
        $embedData = (string)json_encode([
            'redirecturl' => $redirectUrl,
        ], JSON_UNESCAPED_SLASHES);
        $items = '[]';

        return [
            'app_trans_id' => $appTransId,
            'post_data' => [
                // v001/tpe/createorder canonical field names
                'appid' => (int)$this->config->getAppId(),
                'apptransid' => $appTransId,
                'appuser' => $appUser,
                'apptime' => $appTime,
                'amount' => $amount,
                'item' => $items,
                'embeddata' => $embedData,
                'description' => $description,
                'callbackurl' => $callbackUrl,
                // Compatibility aliases for integrations using underscore style keys.
                'app_id' => (int)$this->config->getAppId(),
                'app_trans_id' => $appTransId,
                'app_user' => $appUser,
                'app_time' => $appTime,
                'embed_data' => $embedData,
                'callback_url' => $callbackUrl,
                'mac' => $this->signatureService->buildCreateOrderMac($appTransId, $appUser, $amount, $appTime, $embedData, $items),
            ],
        ];
    }
}
