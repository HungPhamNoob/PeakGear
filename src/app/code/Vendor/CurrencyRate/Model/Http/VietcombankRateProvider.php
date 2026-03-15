<?php
declare(strict_types=1);

namespace Vendor\CurrencyRate\Model\Http;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Vendor\CurrencyRate\Api\RateProviderInterface;
use Vendor\CurrencyRate\Model\Config;

/**
 * Pulls and parses the Vietcombank XML feed through Magento's HTTP client.
 */
class VietcombankRateProvider implements RateProviderInterface
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function fetchRates(): array
    {
        $this->curl->setTimeout(10);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
        $this->curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->curl->addHeader('User-Agent', 'PeakGear CurrencyRate/1.0');
        $this->curl->get($this->config->getFeedUrl());

        if ($this->curl->getStatus() !== 200 || $this->curl->getBody() === '') {
            throw new LocalizedException(__('Unable to fetch currency rate feed.'));
        }

        libxml_use_internal_errors(true);
        $document = simplexml_load_string($this->curl->getBody());

        if (!$document instanceof \SimpleXMLElement) {
            throw new LocalizedException(__('Currency rate feed returned invalid XML.'));
        }

        $allowedCodes = array_flip($this->config->getTrackedCurrencies());
        $rates = [];

        foreach ($document->Exrate as $item) {
            $currencyCode = strtoupper((string)$item['CurrencyCode']);
            if (!isset($allowedCodes[$currencyCode])) {
                continue;
            }

            $rates[$currencyCode] = [
                'name' => (string)$item['CurrencyName'],
                'buy_transfer' => (float)str_replace(',', '', (string)$item['Transfer']),
                'buy_cash' => (float)str_replace(',', '', (string)$item['Buy']),
                'sell' => (float)str_replace(',', '', (string)$item['Sell']),
            ];
        }

        if ($rates === []) {
            throw new LocalizedException(__('Currency rate feed did not contain any supported currencies.'));
        }

        return $rates;
    }
}
