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
        private Curl $curl,
        private Json $json,
        private LoggerInterface $logger
    ) {}

    /**
     * Get shipping fee from GHTK
     */
    public function getFee(string $token, bool $isSandbox, array $params): ?array
    {
        $url = $isSandbox ? self::API_URL_SANDBOX : self::API_URL_PROD;
        
        // Build query string
        $queryString = http_build_query($params);
        $url .= '?' . $queryString;

        try {
            $this->curl->setHeaders([
                'Token' => $token,
                'Content-Type' => 'application/json'
            ]);

            $this->curl->get($url);
            $response = $this->curl->getBody();
            
            $data = $this->json->unserialize($response);
            
            if (isset($data['success']) && $data['success'] === true && isset($data['fee']['fee'])) {
                return $data['fee'];
            }
            
            $this->logger->error('GHTK API Error: ' . $response);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('GHTK API Exception: ' . $e->getMessage());
            return null;
        }
    }
}
