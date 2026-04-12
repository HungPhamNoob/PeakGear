<?php
declare(strict_types=1);

namespace PeakGear\Account\Controller\Wishlist;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;

/**
 * MyAccount Wishlist Controller - forwards to wishlist
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
        $resultForward->setModule('wishlist')
                      ->setController('index')
                      ->forward('index');
        return $resultForward;
    }
}
