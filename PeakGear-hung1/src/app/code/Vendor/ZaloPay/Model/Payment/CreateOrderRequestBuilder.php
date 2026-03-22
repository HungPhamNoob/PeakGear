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
        $appTransId = date('ymd') . '_' . $orderId;
        $appTime = (int)round(microtime(true) * 1000);
        $embedData = (string)json_encode(['redirecturl' => $redirectUrl], JSON_UNESCAPED_SLASHES);
        $items = '[]';

        return [
            'app_trans_id' => $appTransId,
            'post_data' => [
                'app_id' => (int)$this->config->getAppId(),
                'app_trans_id' => $appTransId,
                'app_user' => 'PeakGearUser',
                'app_time' => $appTime,
                'amount' => $amount,
                'item' => $items,
                'embed_data' => $embedData,
                'description' => $description,
                'callback_url' => $callbackUrl,
                'mac' => $this->signatureService->buildCreateOrderMac($appTransId, $amount, $appTime, $embedData, $items),
            ],
        ];
    }
}
