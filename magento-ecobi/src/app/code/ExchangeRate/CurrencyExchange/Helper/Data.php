<?php
namespace ExchangeRate\CurrencyExchange\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    const API_URL = 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=68';
    const MAX_RETRIES = 3;
    const TIMEOUT_SECONDS = 10;
    
    protected $curl;
    protected $logger;
    
    public function __construct(
        Context $context,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        parent::__construct($context);
    }
    
    public function getCurrencyRates()
    {
        $retries = 0;
        while ($retries < self::MAX_RETRIES) {
            try {
                // Configure CURL options
                $this->curl->setOption(CURLOPT_FRESH_CONNECT, true);
                $this->curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                $this->curl->setOption(CURLOPT_TIMEOUT, self::TIMEOUT_SECONDS);
                $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
                $this->curl->setOption(CURLOPT_DNS_CACHE_TIMEOUT, 2);
                $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                $this->curl->setOption(CURLOPT_FOLLOWLOCATION, true);
                $this->curl->setOption(CURLOPT_MAXREDIRS, 3);
                
                // Add headers
                $this->curl->addHeader("Connection", "close");
                $this->curl->addHeader("Cache-Control", "no-cache");
                $this->curl->addHeader("Pragma", "no-cache");
                
                // Make the request
                $startTime = microtime(true);
                $this->curl->get(self::API_URL);
                $response = $this->curl->getBody();
                
                // Check response
                if (empty($response)) {
                    throw new \Exception('Empty response received');
                }
                
                // Log successful request
                $endTime = microtime(true);
                $this->logger->info('Currency API request successful', [
                    'time_taken' => round($endTime - $startTime, 2) . 's',
                    'attempt' => $retries + 1
                ]);
                
                return new \SimpleXMLElement($response);
                
            } catch (\Exception $e) {
                $retries++;
                $this->logger->warning('Currency API request failed', [
                    'attempt' => $retries,
                    'error' => $e->getMessage()
                ]);
                
                if ($retries >= self::MAX_RETRIES) {
                    // Try fallback method as last resort
                    $fallbackResult = $this->getFallbackCurrencyRates();
                    if ($fallbackResult) {
                        return $fallbackResult;
                    }
                    
                    $this->logger->error('All currency rate fetch attempts failed', [
                        'total_attempts' => $retries,
                        'last_error' => $e->getMessage()
                    ]);
                    return null;
                }
                
                // Wait before retrying
                sleep(2);
            }
        }
        
        return null;
    }
    
    protected function getFallbackCurrencyRates()
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => self::TIMEOUT_SECONDS,
                    'protocol_version' => 1.1,
                    'header' => [
                        'Connection: close',
                        'Cache-Control: no-cache',
                        'Pragma: no-cache'
                    ]
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);
            
            $response = @file_get_contents(self::API_URL, false, $context);
            
            if ($response === false) {
                throw new \Exception('Fallback request failed');
            }
            
            return new \SimpleXMLElement($response);
            
        } catch (\Exception $e) {
            $this->logger->error('Fallback method failed: ' . $e->getMessage());
            return null;
        }
    }
}
