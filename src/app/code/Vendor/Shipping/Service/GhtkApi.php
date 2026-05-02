<?php
declare(strict_types=1);

namespace Vendor\Shipping\Service;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class GhtkApi
{
    private const API_URL_PROD = 'https://services.giaohangtietkiem.vn/services/shipment/fee';
    private const API_URL_SANDBOX = 'https://services-staging.htgiohangtietkiem.vn/services/shipment/fee';

    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
        private readonly FeeCache $feeCache
    ) {
    }

    /**
     * Get shipping fee from GHTK
     */
    public function getFee(
        string $token,
        string $clientSource,
        bool $isSandbox,
        array $params,
        int $timeout = 10,
        int $cacheTtl = 300,
        bool $debug = false
    ): ?array
    {
        $cacheKey = sha1((string)json_encode([$isSandbox, $params]));
        $cached = $this->feeCache->load($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $url = $isSandbox ? self::API_URL_SANDBOX : self::API_URL_PROD;
        $url .= '?' . http_build_query($params);

        try {
            $this->curl->setTimeout($timeout);
            $this->curl->setHeaders([
                'Token' => $token,
                'X-Client-Source' => $clientSource,
                'Accept' => 'application/json'
            ]);

            $this->curl->get($url);
            $response = $this->curl->getBody();
            $status = (int)$this->curl->getStatus();

            if ($debug) {
                $this->logger->debug('GHTK fee response.', [
                    'url' => $url,
                    'status' => $status,
                    'response' => $response,
                ]);
            }

            if ($status >= 400 || $response === '') {
                $this->logger->error('GHTK API HTTP error.', ['status' => $status, 'response' => $response]);

                return null;
            }

            $data = $this->json->unserialize($response);
            if (isset($data['success']) && $data['success'] === true && isset($data['fee']) && is_array($data['fee'])) {
                $fee = $data['fee'];
                $this->feeCache->save($cacheKey, $fee, $cacheTtl);

                return $fee;
            }

            $this->logger->error('GHTK API Error: ' . $response);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('GHTK API Exception: ' . $e->getMessage());
            return null;
        }
    }
}
