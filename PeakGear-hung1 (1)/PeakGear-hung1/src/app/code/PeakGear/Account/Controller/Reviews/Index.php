<?php
declare(strict_types=1);

namespace PeakGear\Account\Controller\Reviews;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * MyAccount Reviews Controller - forwards to review/customer
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        /** @var Forward $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $result->setModule('review')
               ->setController('customer')
               ->forward('index');

        return $result;
    }
}
