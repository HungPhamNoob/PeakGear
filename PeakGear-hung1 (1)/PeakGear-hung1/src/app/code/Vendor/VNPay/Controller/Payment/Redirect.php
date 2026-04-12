<?php
declare(strict_types=1);

namespace Vendor\VNPay\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Vendor\VNPay\Model\Payment\VNPay as VNPayModel;
use Psr\Log\LoggerInterface;

class Redirect implements HttpGetActionInterface
{
    private const REDIRECT_CACHE_KEY = 'peakgear_vnpay_redirect_cache';
    private const GATEWAY_STARTED_KEY = 'peakgear_vnpay_gateway_started';

    public function __construct(
        private RedirectFactory     $redirectFactory,
        private CheckoutSession     $checkoutSession,
        private VNPayModel          $vnpayModel,
        private UrlInterface        $url,
        private CartRepositoryInterface $quoteRepository,
        private ManagerInterface    $messageManager,
        private LoggerInterface     $logger
    ) {}

    public function execute()
    {
        $resultRedirect = $this->redirectFactory->create();
        $this->checkoutSession->setData('peakgear_successful_payment_order', null);

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getId()) {
                throw new \RuntimeException('Không tìm thấy đơn hàng');
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

            $returnPath = trim($this->vnpayModel->getReturnUrlPath(), '/');
            if ($returnPath === '') {
                $returnPath = 'vnpay/payment/return/index';
            }
            $returnUrl = $this->url->getUrl($returnPath);
            $ipAddr    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $amount    = (int)round((float)$order->getGrandTotal());

            $redirectUrl = $this->vnpayModel->buildRedirectUrl(
                (string)$order->getIncrementId(),
                $amount,
                'Thanh toan don hang ' . $order->getIncrementId(),
                $returnUrl,
                $ipAddr
            );

            $this->checkoutSession->setData(self::REDIRECT_CACHE_KEY, [
                'order_increment_id' => $incrementId,
                'url' => $redirectUrl,
                'created_at' => time(),
            ]);
            $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, true);
            $this->reactivateQuoteForOrder((int)$order->getQuoteId(), $incrementId);

            return $resultRedirect->setUrl($redirectUrl);
        } catch (\Exception $e) {
            $this->logger->error('VNPay redirect build failed.', [
                'order_increment_id' => isset($order) && $order->getIncrementId() ? $order->getIncrementId() : null,
                'exception' => $e
            ]);
            $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
            $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
            $this->messageManager->addErrorMessage(__('Không thể kết nối đến cổng thanh toán VNPay. Vui lòng thử lại.'));

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
            $this->logger->warning('VNPay quote reactivation failed.', [
                'quote_id' => $quoteId,
                'order_increment_id' => $incrementId,
                'exception' => $exception,
            ]);
        }
    }
}
