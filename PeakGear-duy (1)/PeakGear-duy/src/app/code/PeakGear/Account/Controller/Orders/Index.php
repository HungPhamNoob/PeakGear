<?php
declare(strict_types=1);

namespace PeakGear\Account\Controller\Orders;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Forward;

/**
 * MyAccount Orders Controller - forwards to sales/order/history
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory
    ) {
    }

    public function execute(): Forward
    {
        /** @var Forward $resultForward */
        $resultForward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $resultForward->setModule('sales')
                      ->setController('order')
                      ->forward('history');
        return $resultForward;
    }
}
