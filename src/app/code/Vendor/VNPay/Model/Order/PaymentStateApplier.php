<?php
declare(strict_types=1);

namespace Vendor\VNPay\Model\Order;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Applies idempotent payment result transitions for VNPay returns.
 */
class PaymentStateApplier
{
    private const CANCELLATION_STATUSES = ['cancellation_requested', 'cancellation_approved'];

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function markSuccessful(string $incrementId, string $transactionNo): void
    {
        $order = $this->getByIncrementId($incrementId);
        if (in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
            return;
        }

        $payment = $order->getPayment();
        if ($payment) {
            $payment->setTransactionId($transactionNo);
            $payment->setAdditionalInformation('peakgear_vnpay_transaction_no', $transactionNo);
        }

        $status = in_array((string)$order->getStatus(), self::CANCELLATION_STATUSES, true)
            ? (string)$order->getStatus()
            : Order::STATE_PROCESSING;
        $order->setState(Order::STATE_PROCESSING)
            ->setStatus($status)
            ->addCommentToStatusHistory(
                __('VNPay payment confirmed. TransactionNo: %1', $transactionNo)->render(),
                $status
            );

        $this->orderRepository->save($order);
    }

    /**
     * @throws LocalizedException
     */
    public function markFailed(string $incrementId, string $responseCode): void
    {
        $order = $this->getByIncrementId($incrementId);
        if (in_array(
            $order->getState(),
            [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CANCELED, Order::STATE_CLOSED],
            true
        )) {
            return;
        }

        $payment = $order->getPayment();
        if ($payment) {
            $lastCode = (string)$payment->getAdditionalInformation('peakgear_vnpay_last_incomplete_code');
            if ($lastCode === $responseCode) {
                return;
            }
            $payment->setAdditionalInformation('peakgear_vnpay_last_incomplete_code', $responseCode);
        }

        $status = in_array((string)$order->getStatus(), self::CANCELLATION_STATUSES, true)
            ? (string)$order->getStatus()
            : Order::STATE_PENDING_PAYMENT;
        $order->setState(Order::STATE_PENDING_PAYMENT)
            ->setStatus($status)
            ->addCommentToStatusHistory(
                __('VNPay payment was not completed. Response code: %1. The cart was restored.', $responseCode)->render(),
                $status
            );

        $this->orderRepository->save($order);
    }

    /**
     * @throws LocalizedException
     */
    private function getByIncrementId(string $incrementId): OrderInterface
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $orders = $this->orderRepository->getList(
            $searchCriteriaBuilder->addFilter('increment_id', $incrementId)->create()
        )->getItems();
        $order = reset($orders);

        if (!$order instanceof OrderInterface) {
            throw new LocalizedException(__('Unable to locate order %1.', $incrementId));
        }

        return $order;
    }
}
