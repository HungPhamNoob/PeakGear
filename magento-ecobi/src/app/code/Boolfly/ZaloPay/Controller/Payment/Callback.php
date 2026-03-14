<?php
namespace Boolfly\ZaloPay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Boolfly\ZaloPay\Helper\Data as ZaloPayHelper;
use Boolfly\ZaloPay\Gateway\Request\AbstractDataBuilder;

class Callback extends Action implements CsrfAwareActionInterface
{
  /**
   * @var OrderFactory
   */
  private $orderFactory;

  /**
   * @var LoggerInterface
   */
  private $logger;

  /**
   * @var ZaloPayHelper
   */
  private $zaloPayHelper;

  /**
   * @var ConfigInterface
   */
  private $config;

  public function __construct(
    Context $context,
    OrderFactory $orderFactory,
    LoggerInterface $logger,
    ZaloPayHelper $zaloPayHelper,
    ConfigInterface $config
  ) {
    parent::__construct($context);
    $this->orderFactory = $orderFactory;
    $this->logger = $logger;
    $this->zaloPayHelper = $zaloPayHelper;
    $this->config = $config;
  }

  /**
   * @inheritDoc
   */
  public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
  {
    return null;
  }

  /**
   * @inheritDoc
   */
  public function validateForCsrf(RequestInterface $request): ?bool
  {
    return true;
  }

  public function execute()
  {
    try {
      $response = $this->getRequest()->getParams();
      $this->zaloPayHelper->debug($response, 'ZaloPay Callback Response');

      // Validate response data
      if (empty($response) || !isset($response['data']) || !isset($response['mac'])) {
        throw new LocalizedException(__('Invalid callback data'));
      }

      // Verify MAC
      $data = $response['data'];
      $requestMac = $response['mac'];
      $key2 = $this->config->getValue(AbstractDataBuilder::KEY_2);
      $mac = hash_hmac('sha256', $data, $key2);

      if ($mac !== $requestMac) {
        throw new LocalizedException(__('Invalid MAC'));
      }

      // Decode data
      $callbackData = json_decode($data, true);
      if (!$callbackData) {
        throw new LocalizedException(__('Invalid JSON data'));
      }

      // Get order
      $appTransId = $callbackData['app_trans_id'];
      $orderId = substr($appTransId, strpos($appTransId, '_') + 1);
      $order = $this->orderFactory->create()->loadByIncrementId($orderId);

      if (!$order->getId()) {
        throw new LocalizedException(__('Order not found'));
      }

      // Check payment status
      if ($callbackData['status'] == 1) { // Payment successful
        if ($order->getState() == Order::STATE_PENDING_PAYMENT) {
          $order->setState(Order::STATE_PROCESSING)
            ->setStatus(Order::STATE_PROCESSING)
            ->addStatusHistoryComment('ZaloPay payment completed. Transaction ID: ' . $callbackData['zp_trans_id'])
            ->save();
        }
        $result = [
          'return_code' => 1,
          'return_message' => 'success'
        ];
      } else { // Payment failed
        if ($order->getState() == Order::STATE_PENDING_PAYMENT) {
          $order->setState(Order::STATE_CANCELED)
            ->setStatus(Order::STATE_CANCELED)
            ->addStatusHistoryComment('ZaloPay payment failed. Reason: ' . ($callbackData['error_message'] ?? 'Unknown error'))
            ->save();
        }
        $result = [
          'return_code' => 0,
          'return_message' => 'failed'
        ];
      }

      $this->zaloPayHelper->debug($result, 'ZaloPay Callback Response');
      return $this->getResponse()
        ->setHeader('Content-type', 'application/json')
        ->setBody(json_encode($result));

    } catch (\Exception $e) {
      $this->logger->critical('ZaloPay Callback Error: ' . $e->getMessage());
      $this->zaloPayHelper->debug([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ], 'ZaloPay Callback Error');

      return $this->getResponse()
        ->setHeader('Content-type', 'application/json')
        ->setBody(json_encode([
          'return_code' => 0,
          'return_message' => $e->getMessage()
        ]));
    }
  }
}