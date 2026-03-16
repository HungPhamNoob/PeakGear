<?php
declare(strict_types=1);

namespace PeakGear\Cart\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly ForwardFactory $resultForwardFactory
    ) {
    }

    public function execute(): Forward
    {
        $result = $this->resultForwardFactory->create();
        $result->setModule('checkout')
               ->setController('cart')
               ->forward('index');

        return $result;
    }
}
