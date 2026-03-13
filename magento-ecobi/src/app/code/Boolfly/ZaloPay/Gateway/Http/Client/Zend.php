<?php
namespace Boolfly\ZaloPay\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;

class Zend implements ClientInterface
{
  /**
   * @var Logger
   */
  private $logger;

  /**
   * @var GuzzleClientInterface
   */
  private $client;

  /**
   * @param Logger $logger
   * @param GuzzleClientInterface $client
   */
  public function __construct(
    Logger $logger,
    GuzzleClientInterface $client
  ) {
    $this->logger = $logger;
    $this->client = $client;
  }

  /**
   * @param TransferInterface $transferObject
   * @return array
   */
  public function placeRequest(TransferInterface $transferObject)
  {
    $log = [
      'request' => $transferObject->getBody(),
      'request_uri' => $transferObject->getUri()
    ];

    $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/zalopay_debug.log');
    $logger = new \Zend_Log();
    $logger->addWriter($writer);

    $result = [];

    try {
      // Get URI and params
      $uri = $transferObject->getUri();
      $params = $transferObject->getBody();

      // Build query string without encoding
      $queryParts = [];
      foreach ($params as $key => $value) {
        $queryParts[] = $key . '=' . $value;
      }
      $queryString = implode('&', $queryParts);

      // Append query string to URI
      $fullUrl = $uri . (strpos($uri, '?') === false ? '?' : '&') . $queryString;

      $logger->info("\n=== {Full URI} " . date('Y-m-d H:i:s') . " ===");
      $logger->info($fullUrl);
      $logger->info("=== End {Full URI} ===\n");

      // Send request using Guzzle
      $response = $this->client->request('POST', $fullUrl, [
        'headers' => $transferObject->getHeaders(),
        'verify' => false,  // Disable SSL verification
        'body' => ''        // Empty body
      ]);

      $responseBody = $response->getBody()->getContents();
      $result = json_decode($responseBody, true);

      $log['response'] = $responseBody;

    } catch (\Exception $e) {
      $log['error'] = $e->getMessage();
      $log['trace'] = $e->getTraceAsString();
      throw $e;
    } finally {
      $this->logger->debug($log);
    }

    return $result;
  }
}