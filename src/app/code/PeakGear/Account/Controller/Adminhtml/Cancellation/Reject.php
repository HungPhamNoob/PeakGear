<?php
declare(strict_types=1);

namespace PeakGear\Account\Controller\Adminhtml\Cancellation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use PeakGear\Account\Model\OrderCancellation\ReviewRequest;

class Reject extends Action implements HttpGetActionInterface
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
            $this->reviewRequest->reject($orderId);
            $this->messageManager->addSuccessMessage(__('Đã từ chối yêu cầu hủy đơn.'));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
