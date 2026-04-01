<?php
declare(strict_types=1);

namespace PeakGear\Account\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;

/**
 * MyAccount Index Controller - forwards to customer/account
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
                      ->forward('index');
        return $resultForward;
    }
}
