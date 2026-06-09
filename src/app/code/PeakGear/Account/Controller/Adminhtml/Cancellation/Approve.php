<?php
declare(strict_types=1);

namespace PeakGear\Account\Controller\Adminhtml\Cancellation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use PeakGear\Account\Model\OrderCancellation\ReviewRequest;

class Approve extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Sales::cancel';

    public function __construct(
        Context $context,
        private readonly ReviewRequest $reviewRequest
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');

        try {
            $order = $this->reviewRequest->approve($orderId);
            $this->messageManager->addSuccessMessage(
                $order->getState() === \Magento\Sales\Model\Order::STATE_CANCELED
                    ? __('Đã duyệt yêu cầu và hủy đơn.')
                    : __('Đã duyệt yêu cầu. Vui lòng thực hiện hoàn tiền/Credit Memo cho khách.')
            );
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
