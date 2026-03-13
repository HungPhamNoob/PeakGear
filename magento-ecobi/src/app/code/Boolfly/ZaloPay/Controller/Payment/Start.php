<?php
namespace Boolfly\ZaloPay\Controller\Payment;

use Boolfly\ZaloPay\Gateway\Helper\TransactionReader;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Session\SessionManager;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\PaymentFailuresInterface;
use Psr\Log\LoggerInterface;
use Magento\Quote\Api\CartManagementInterface;

class Start extends AppAction
{
  /**
   * @var CommandPoolInterface
   */
  private $commandPool;

  /**
   * @var LoggerInterface
   */
  private $logger;

  /**
   * @var PaymentDataObjectFactory
   */
  private $paymentDataObjectFactory;

  /**
   * @var Session
   */
  private $checkoutSession;

  /**
   * @var SessionManager
   */
  private $sessionManager;

  /**
   * @var PaymentFailuresInterface
   */
  private $paymentFailures;

  /**
   * @var CartRepositoryInterface
   */
  private $quoteRepository;

  /**
   * @var CartManagementInterface
   */
  private $cartManagement;

  /**
   * @var OrderRepositoryInterface
   */
  private $orderRepository;

  /**
   * @var ResultFactory
   */
  protected $resultFactory;

  public function __construct(
    Context $context,
    CommandPoolInterface $commandPool,
    LoggerInterface $logger,
    OrderRepositoryInterface $orderRepository,
    PaymentDataObjectFactory $paymentDataObjectFactory,
    Session $checkoutSession,
    CartRepositoryInterface $quoteRepository,
    SessionManager $sessionManager,
    CartManagementInterface $cartManagement,
    ResultFactory $resultFactory,
    PaymentFailuresInterface $paymentFailures = null
  ) {
    parent::__construct($context);
    $this->commandPool = $commandPool;
    $this->logger = $logger;
    $this->quoteRepository = $quoteRepository;
    $this->paymentDataObjectFactory = $paymentDataObjectFactory;
    $this->checkoutSession = $checkoutSession;
    $this->sessionManager = $sessionManager;
    $this->paymentFailures = $paymentFailures ?: $this->_objectManager->get(PaymentFailuresInterface::class);
    $this->cartManagement = $cartManagement;
    $this->orderRepository = $orderRepository;
    $this->resultFactory = $resultFactory;
  }

  public function execute()
  {
    try {
      $lastQuoteId = $this->checkoutSession->getLastQuoteId();
      $orderId = $this->checkoutSession->getLastOrderId();
      $lastRealOrderId = $this->checkoutSession->getLastRealOrderId();

      $this->logger->debug('Starting ZaloPay payment process');
      $this->logger->debug('LastQuoteId: ' . $lastQuoteId);
      $this->logger->debug('OrderId: ' . $orderId);
      $this->logger->debug('LastRealOrderId: ' . $lastRealOrderId);

      if ($orderId) {
        // Restore quote
        if ($lastQuoteId) {
          $this->checkoutSession->setQuoteId($lastQuoteId);
          $this->sessionManager->setData('quote_id_', $lastQuoteId);

          try {
            $quote = $this->quoteRepository->get($lastQuoteId);
            if (!$quote->getIsActive()) {
              $quote->setIsActive(true);
              $this->quoteRepository->save($quote);
            }
          } catch (\Exception $e) {
            $this->logger->critical('Error restoring quote: ' . $e->getMessage());
          }
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderRepository->get($orderId);
        $payment = $order->getPayment();

        $this->logger->debug('Order details:');
        $this->logger->debug('Order ID: ' . $order->getId());
        $this->logger->debug('Order Status: ' . $order->getStatus());
        $this->logger->debug('Order Total: ' . $order->getTotalDue());
        $this->logger->debug('Payment Method: ' . $payment->getMethod());

        ContextHelper::assertOrderPayment($payment);
        $paymentDataObject = $this->paymentDataObjectFactory->create($payment);

        try {
          $this->logger->debug('Executing get_pay_url command');
          $commandResult = $this->commandPool->get('get_pay_url')->execute(
            [
              'payment' => $paymentDataObject,
              'amount' => $order->getTotalDue(),
            ]
          );

          $this->logger->debug('Command result: ' . print_r($commandResult->get(), true));

          $payUrl = TransactionReader::readPayUrl($commandResult->get());
          $this->logger->debug('PayUrl: ' . $payUrl);

          if ($payUrl) {
            // Save session data before redirect
            $this->checkoutSession
              ->setLastQuoteId($lastQuoteId)
              ->setLastSuccessQuoteId($lastQuoteId)
              ->setLastOrderId($orderId)
              ->setLastRealOrderId($lastRealOrderId);

            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)
              ->setUrl($payUrl);
          }

          $this->logger->debug('No PayUrl returned');
          return $this->resultFactory->create(ResultFactory::TYPE_JSON)
            ->setData([
              'success' => false,
              'error' => true,
              'message' => __('Unable to get ZaloPay payment URL.')
            ]);
        } catch (\Exception $e) {
          $this->logger->critical('Error executing get_pay_url command: ' . $e->getMessage());
          $this->logger->critical('Stack trace: ' . $e->getTraceAsString());
          throw $e;
        }
      }

      $this->logger->debug('No order ID found');
      return $this->resultFactory->create(ResultFactory::TYPE_JSON)
        ->setData([
          'success' => false,
          'error' => true,
          'message' => __('Order not found.')
        ]);

    } catch (\Exception $e) {
      $this->logger->critical('ZaloPay Error: ' . $e->getMessage());
      $this->logger->critical('Stack trace: ' . $e->getTraceAsString());

      return $this->resultFactory->create(ResultFactory::TYPE_JSON)
        ->setData([
          'success' => false,
          'error' => true,
          'message' => $e->getMessage()
        ]);
    }
  }
}