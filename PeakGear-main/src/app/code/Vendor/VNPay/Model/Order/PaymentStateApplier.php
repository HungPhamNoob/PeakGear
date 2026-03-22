<?php
declare(strict_types=1);

namespace Vendor\VNPay\Model\Order;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Applies idempotent success/failure transitions for VNPay returns.
 */
class PaymentStateApplier
{
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

        $order->setState(Order::STATE_PROCESSING)
            ->setStatus(Order::STATE_PROCESSING)
            ->addCommentToStatusHistory(
                __('VNPay payment confirmed. TransactionNo: %1', $transactionNo)->render()
            );

        $this->orderRepository->save($order);
    }

    /**
     * @throws LocalizedException
     */
    public function markFailed(string $incrementId, string $responseCode): void
    {
        $order = $this->getByIncrementId($incrementId);
        if (in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
            return;
        }

        if ($order->getState() !== Order::STATE_CANCELED) {
            $order->setState(Order::STATE_CANCELED)
                ->setStatus(Order::STATE_CANCELED)
                ->addCommentToStatusHistory(
                    __('VNPay payment failed. Response code: %1', $responseCode)->render()
                );

            $this->orderRepository->save($order);
        }
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
