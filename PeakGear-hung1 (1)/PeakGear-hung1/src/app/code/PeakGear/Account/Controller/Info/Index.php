<?php
declare(strict_types=1);

namespace PeakGear\Account\Controller\Info;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;

/**
 * MyAccount Info Controller - forwards to customer/account/edit
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
        $resultForward->setModule('customer')
                      ->setController('account')
                      ->forward('edit');
        return $resultForward;
    }
}
