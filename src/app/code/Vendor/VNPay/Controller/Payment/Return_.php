<?php
declare(strict_types=1);

namespace Vendor\VNPay\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Vendor\VNPay\Model\Payment\VNPay as VNPayModel;
use Vendor\VNPay\Model\Order\PaymentStateApplier;
use Psr\Log\LoggerInterface;

class Return_ implements HttpGetActionInterface
{
    public function __construct(
        private RequestInterface        $request,
        private RedirectFactory         $redirectFactory,
        private VNPayModel              $vnpayModel,
        private PaymentStateApplier     $paymentStateApplier,
        private ManagerInterface        $messageManager,
        private LoggerInterface         $logger
    ) {}

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

                $this->messageManager->addSuccessMessage(__('Thanh toán VNPay thành công. Đơn hàng #%1 đang được xử lý.', $orderId));
                return $resultRedirect->setPath('checkout/onepage/success');
            }

            $responseCode = (string)($params['vnp_ResponseCode'] ?? 'unknown');
            $this->paymentStateApplier->markFailed($orderId, $responseCode);

            $this->messageManager->addErrorMessage(
                __('Thanh toán VNPay không thành công (mã: %1). Đơn hàng đã được cập nhật.', $responseCode)
            );

            return $resultRedirect->setPath('cart');
        } catch (\Exception $e) {
            $this->logger->error('VNPay return handling failed.', [
                'params' => $params,
                'exception' => $e
            ]);
            $this->messageManager->addErrorMessage(__('Có lỗi xảy ra khi xác thực thanh toán VNPay.'));
            return $resultRedirect->setPath('cart');
        }
    }
}
