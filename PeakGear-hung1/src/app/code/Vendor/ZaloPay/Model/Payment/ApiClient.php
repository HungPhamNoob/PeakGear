<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Model\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Vendor\ZaloPay\Model\Config;

/**
 * Handles remote communication with the ZaloPay create-order endpoint.
 */
class ApiClient
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, int|string> $postData
     * @return array<string, mixed>
     */
    public function createOrder(array $postData): array
    {
        $this->curl->setTimeout(10);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
        $this->curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->curl->post($this->config->getApiUrl(), $postData);

        if ($this->curl->getStatus() !== 200) {
            throw new LocalizedException(__('ZaloPay gateway returned HTTP %1.', $this->curl->getStatus()));
        }

        $response = json_decode($this->curl->getBody(), true);
        if (!is_array($response)) {
            throw new LocalizedException(__('ZaloPay gateway returned an invalid response.'));
        }

        return $response;
    }
}
