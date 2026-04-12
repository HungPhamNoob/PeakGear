<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Vendor\ZaloPay\Model\Payment\ZaloPay as ZaloPayModel;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class Create implements HttpGetActionInterface
{
    private const REDIRECT_CACHE_KEY = 'peakgear_zalopay_redirect_cache';
    private const GATEWAY_STARTED_KEY = 'peakgear_zalopay_gateway_started';

    public function __construct(
        private RedirectFactory  $redirectFactory,
        private CheckoutSession  $checkoutSession,
        private ZaloPayModel     $zaloPayModel,
        private UrlInterface     $url,
        private OrderRepositoryInterface $orderRepository,
        private CartRepositoryInterface $quoteRepository,
        private ManagerInterface $messageManager,
        private LoggerInterface  $logger
    ) {}

    public function execute()
    {
        $resultRedirect = $this->redirectFactory->create();
        $this->checkoutSession->setData('peakgear_successful_payment_order', null);

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getId()) {
                throw new \RuntimeException('Unable to locate the last placed order.');
            }

            $incrementId = (string)$order->getIncrementId();
            $cached = $this->checkoutSession->getData(self::REDIRECT_CACHE_KEY);

            if (is_array($cached)
                && ($cached['order_increment_id'] ?? '') === $incrementId
                && is_string($cached['url'] ?? null)
                && ($cached['url'] ?? '') !== ''
                && ((int)($cached['created_at'] ?? 0)) >= (time() - 1800)) {
                $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, true);
                $this->reactivateQuoteForOrder((int)$order->getQuoteId(), $incrementId);

                return $resultRedirect->setUrl((string)$cached['url']);
            }

            $callbackUrl = $this->url->getUrl('zalopay/payment/callback');
            $redirectUrl = $this->url->getUrl('zalopay/payment/result');
            $amount      = (int)round((float)$order->getGrandTotal());

            $result = $this->zaloPayModel->createOrder(
                $incrementId,
                $amount,
                'PeakGear - Thanh toan don hang #' . $incrementId,
                $callbackUrl,
                $redirectUrl
            );

            $payment = $order->getPayment();
            if ($payment) {
                $payment->setAdditionalInformation('peakgear_zalopay_app_trans_id', (string)$result['app_trans_id']);
                $this->orderRepository->save($order);
            }

            $this->checkoutSession->setData(self::REDIRECT_CACHE_KEY, [
                'order_increment_id' => $incrementId,
                'url' => (string)$result['order_url'],
                'app_trans_id' => (string)$result['app_trans_id'],
                'created_at' => time(),
            ]);
            $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, true);
            $this->reactivateQuoteForOrder((int)$order->getQuoteId(), $incrementId);

            return $resultRedirect->setUrl($result['order_url']);
        } catch (\Exception $e) {
            $this->logger->error('ZaloPay create redirect failed.', [
                'order_increment_id' => isset($order) && $order->getIncrementId() ? $order->getIncrementId() : null,
                'exception' => $e
            ]);
            $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
            $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
            $this->messageManager->addErrorMessage(__('Không thể kết nối đến cổng ZaloPay. Vui lòng thử lại.'));

            return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
        }
    }

    private function reactivateQuoteForOrder(int $quoteId, string $incrementId): void
    {
        if ($quoteId <= 0) {
            return;
        }

        try {
            $quote = $this->quoteRepository->get($quoteId);
            $quote->setIsActive(true);
            $quote->setReservedOrderId(null);
            $this->quoteRepository->save($quote);

            $this->checkoutSession->replaceQuote($quote);
            $this->checkoutSession->setLastRealOrderId($incrementId);
        } catch (\Exception $exception) {
            $this->logger->warning('ZaloPay quote reactivation failed.', [
                'quote_id' => $quoteId,
                'order_increment_id' => $incrementId,
                'exception' => $exception,
            ]);
        }
    }
}
