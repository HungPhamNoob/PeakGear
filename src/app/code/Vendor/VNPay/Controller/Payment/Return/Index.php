<?php
declare(strict_types=1);

namespace Vendor\VNPay\Controller\Payment\Return;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use Vendor\VNPay\Model\Order\PaymentStateApplier;
use Vendor\VNPay\Model\Payment\VNPay as VNPayModel;

class Index implements HttpGetActionInterface
{
    private const REDIRECT_CACHE_KEY = 'peakgear_vnpay_redirect_cache';
    private const GATEWAY_STARTED_KEY = 'peakgear_vnpay_gateway_started';

    public function __construct(
        private RequestInterface $request,
        private CheckoutSession $checkoutSession,
        private RedirectFactory $redirectFactory,
        private VNPayModel $vnpayModel,
        private PaymentStateApplier $paymentStateApplier,
        private ManagerInterface $messageManager,
        private LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $resultRedirect = $this->redirectFactory->create();
        $params = $this->request->getParams();

        try {
            $success = $this->vnpayModel->verifyCallback($params);

            $orderId = $params['vnp_TxnRef'] ?? '';
            if ($orderId === '') {
                throw new \RuntimeException('Missing VNPay order reference.');
            }

            if ($success) {
                $transactionNo = (string)($params['vnp_TransactionNo'] ?? 'N/A');
                $this->paymentStateApplier->markSuccessful($orderId, $transactionNo);
                $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
                $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
                $this->checkoutSession->clearQuote();
                $this->checkoutSession->setData('peakgear_successful_payment_order', $orderId);

                $this->messageManager->addSuccessMessage(__('Thanh toán VNPay thành công. Đơn hàng #%1 đang được xử lý.', $orderId));
                return $resultRedirect->setPath('checkout/successful-payment');
            }

            $responseCode = (string)($params['vnp_ResponseCode'] ?? 'unknown');
            $this->paymentStateApplier->markFailed($orderId, $responseCode);
            $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
            $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
            $this->checkoutSession->setData('peakgear_successful_payment_order', null);
            $this->checkoutSession->restoreQuote();

            $this->messageManager->addErrorMessage(
                __('Thanh toán VNPay không thành công (mã: %1). Đơn hàng đã được cập nhật.', $responseCode)
            );

            return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
        } catch (\Exception $e) {
            $this->logger->error('VNPay return handling failed.', [
                'params' => $params,
                'exception' => $e,
            ]);
            $this->checkoutSession->setData(self::GATEWAY_STARTED_KEY, false);
            $this->checkoutSession->unsetData(self::REDIRECT_CACHE_KEY);
            $this->checkoutSession->setData('peakgear_successful_payment_order', null);
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addErrorMessage(__('Có lỗi xảy ra khi xác thực thanh toán VNPay.'));
            return $resultRedirect->setPath('checkout', ['_fragment' => 'payment']);
        }
    }
}
