<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;

use Magento\Checkout\Model\Session as CheckoutSession;
use Vendor\ZaloPay\Model\Payment\ZaloPay as ZaloPayModel;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class Create implements HttpGetActionInterface
{
    public function __construct(
        private RedirectFactory  $redirectFactory,
        private CheckoutSession  $checkoutSession,
        private ZaloPayModel     $zaloPayModel,
        private UrlInterface     $url,
        private ManagerInterface $messageManager,
        private LoggerInterface  $logger
    ) {}

    public function execute()
    {
        $resultRedirect = $this->redirectFactory->create();

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getId()) {
                throw new \RuntimeException('Unable to locate the last placed order.');
            }

            $callbackUrl = $this->url->getUrl('zalopay/payment/callback');
            $redirectUrl = $this->url->getUrl('checkout/onepage/success');
            $amount      = (int)round((float)$order->getGrandTotal());

            $result = $this->zaloPayModel->createOrder(
                (string)$order->getIncrementId(),
                $amount,
                'Thanh toán đơn hàng PeakGear #' . $order->getIncrementId(),
                $callbackUrl,
                $redirectUrl
            );

            return $resultRedirect->setUrl($result['order_url']);
        } catch (\Exception $e) {
            $this->logger->error('ZaloPay create redirect failed.', [
                'order_increment_id' => isset($order) && $order->getIncrementId() ? $order->getIncrementId() : null,
                'exception' => $e
            ]);
            $this->messageManager->addErrorMessage(__('Không thể kết nối đến cổng ZaloPay. Vui lòng thử lại.'));
            return $resultRedirect->setPath('cart');
        }
    }
}
