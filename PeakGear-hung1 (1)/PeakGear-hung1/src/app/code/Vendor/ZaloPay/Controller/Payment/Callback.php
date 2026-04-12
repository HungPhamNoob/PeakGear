<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Controller\Payment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\JsonFactory;
use Vendor\ZaloPay\Model\Payment\ZaloPay as ZaloPayModel;
use Vendor\ZaloPay\Model\Order\PaymentStateApplier;
use Psr\Log\LoggerInterface;

class Callback implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private HttpRequest             $request,
        private JsonFactory            $jsonFactory,
        private ZaloPayModel           $zaloPayModel,
        private PaymentStateApplier    $paymentStateApplier,
        private LoggerInterface        $logger
    ) {}

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null; // Disable CSRF for payment callback
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $rawBody = $this->request->getContent();
        $data   = json_decode($rawBody, true);

        try {
            if (!is_array($data)) {
                throw new \RuntimeException('Invalid callback payload.');
            }

            if (!$this->zaloPayModel->verifyCallback($data)) {
                return $result->setData(['return_code' => -1, 'return_message' => 'mac not equal']);
            }

            $cbData = json_decode($data['data'] ?? '{}', true);
            if (!is_array($cbData)) {
                throw new \RuntimeException('Missing callback data payload.');
            }

            $appTransId = (string)($cbData['apptransid'] ?? $cbData['app_trans_id'] ?? '');
            if ($appTransId === '') {
                throw new \RuntimeException('Missing app_trans_id in callback.');
            }

            $parts   = explode('_', $appTransId, 2);
            $orderPart = $parts[1] ?? '';
            $orderId = explode('-', $orderPart, 2)[0] ?? '';
            if ($orderId === '') {
                throw new \RuntimeException('Unable to extract order increment id.');
            }

            $this->paymentStateApplier->markSuccessful($orderId, $appTransId);

            return $result->setData(['return_code' => 1, 'return_message' => 'success']);
        } catch (\Exception $e) {
            $this->logger->error('ZaloPay callback handling failed.', [
                'payload' => $rawBody,
                'exception' => $e
            ]);
            return $result->setData(['return_code' => 0, 'return_message' => 'callback error']);
        }
    }
}
