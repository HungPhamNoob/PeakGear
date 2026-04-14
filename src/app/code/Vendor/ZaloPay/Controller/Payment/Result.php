<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Vendor\ZaloPay\Model\Order\PaymentStateApplier;
use Vendor\ZaloPay\Model\Payment\ZaloPay as ZaloPayModel;

class Result implements HttpGetActionInterface
{
    private const REDIRECT_CACHE_KEY = 'peakgear_zalopay_redirect_cache';
    private const GATEWAY_STARTED_KEY = 'peakgear_zalopay_gateway_started';

    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly ZaloPayModel $zaloPayModel,
        private readonly PaymentStateApplier $paymentStateApplier,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $resultRedirect = $this->redirectFactory->create();
        $cached = $this->checkoutSession->getData(self::REDIRECT_CACHE_KEY);

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getId()) {
                throw new \RuntimeException('Unable to locate the last placed order for ZaloPay result.');
            }

            if (in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
                $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
                $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
                $this->checkoutSession->clearQuote();
                $this->checkoutSession->setData('peakgear_successful_payment_order', (string)$order->getIncrementId());
                return $resultRedirect->setPath('checkout/successful-payment');
            }

            $payment = $order->getPayment();
            $appTransId = $payment ? (string)$payment->getAdditionalInformation('peakgear_zalopay_app_trans_id') : '';

            if ($appTransId === '' && is_array($cached)) {
                $appTransId = (string)($cached['app_trans_id'] ?? '');
            }

            if ($appTransId === '') {
                $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
                $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
                $this->checkoutSession->setData('peakgear_successful_payment_order', null);
                $this->checkoutSession->restoreQuote();
                $this->messageManager->addWarningMessage(__('Không tìm thấy mã giao dịch ZaloPay để kiểm tra trạng thái.'));
                return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
            }

            $queryResult = $this->zaloPayModel->queryOrder($appTransId);
            if ($this->zaloPayModel->isQueryPaid($queryResult)) {
                $this->paymentStateApplier->markSuccessful((string)$order->getIncrementId(), $appTransId);
                $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
                $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
                $this->checkoutSession->clearQuote();
                $this->checkoutSession->setData('peakgear_successful_payment_order', (string)$order->getIncrementId());
                return $resultRedirect->setPath('checkout/successful-payment');
            } else {
                $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
                $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
                $this->checkoutSession->setData('peakgear_successful_payment_order', null);
                $this->checkoutSession->restoreQuote();
                $this->messageManager->addWarningMessage(__('Thanh toán ZaloPay không thành công, đã hết thời gian hoặc đã bị hủy. Vui lòng thử lại.'));
                return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
            }
        } catch (\Exception $e) {
            $this->logger->error('ZaloPay return query failed.', ['exception' => $e]);
            $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
            $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
            $this->checkoutSession->setData('peakgear_successful_payment_order', null);
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addWarningMessage(__('Không thể kiểm tra trạng thái thanh toán ngay lúc này. Vui lòng thử lại.'));

            return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
        }
    }
}
