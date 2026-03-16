<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Model\Order;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Applies idempotent state transitions for successful ZaloPay callbacks.
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
    public function markSuccessful(string $incrementId, string $appTransId): void
    {
        $order = $this->getByIncrementId($incrementId);
        if (in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
            return;
        }

        $payment = $order->getPayment();
        if ($payment) {
            $payment->setTransactionId($appTransId);
            $payment->setAdditionalInformation('peakgear_zalopay_app_trans_id', $appTransId);
        }

        $order->setState(Order::STATE_PROCESSING)
            ->setStatus(Order::STATE_PROCESSING)
            ->addCommentToStatusHistory(
                __('ZaloPay payment confirmed. AppTransId: %1', $appTransId)->render()
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
