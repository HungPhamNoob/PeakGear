<?php
declare(strict_types=1);

namespace Vendor\VNPay\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Checkout\Model\Session as CheckoutSession;
use Vendor\VNPay\Model\Payment\VNPay as VNPayModel;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class Redirect implements HttpGetActionInterface
{
    public function __construct(
        private RequestInterface    $request,
        private RedirectFactory     $redirectFactory,
        private CheckoutSession     $checkoutSession,
        private VNPayModel          $vnpayModel,
        private UrlInterface        $url,
        private ManagerInterface    $messageManager,
        private LoggerInterface     $logger
    ) {}

    public function execute()
    {
        $resultRedirect = $this->redirectFactory->create();

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getId()) {
                throw new \RuntimeException('Không tìm thấy đơn hàng');
            }

            $returnUrl = $this->url->getUrl('vnpay/payment/return');
            $ipAddr    = $this->request->getServer('REMOTE_ADDR', '127.0.0.1');
            $amount    = (int)round((float)$order->getGrandTotal());

            $redirectUrl = $this->vnpayModel->buildRedirectUrl(
                (string)$order->getIncrementId(),
                $amount,
                'Thanh toan don hang ' . $order->getIncrementId(),
                $returnUrl,
                $ipAddr
            );

            return $resultRedirect->setUrl($redirectUrl);
        } catch (\Exception $e) {
            $this->logger->error('VNPay redirect build failed.', [
                'order_increment_id' => isset($order) && $order->getIncrementId() ? $order->getIncrementId() : null,
                'exception' => $e
            ]);
            $this->messageManager->addErrorMessage(__('Không thể kết nối đến cổng thanh toán VNPay. Vui lòng thử lại.'));
            return $resultRedirect->setPath('checkout/cart');
        }
    }
}
