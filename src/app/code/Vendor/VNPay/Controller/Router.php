<?php
declare(strict_types=1);

namespace Vendor\VNPay\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;

class Router implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory
    ) {
    }

    public function match(RequestInterface $request)
    {
        if (!$request instanceof HttpRequest) {
            return null;
        }

        // Avoid re-matching requests that were already rewritten by this router.
        if ($request->getModuleName() === 'checkout'
            && $request->getControllerName() === 'successfulpayment'
            && $request->getActionName() === 'index') {
            return null;
        }

        $identifier = trim((string)$request->getPathInfo(), '/');
        if (!preg_match('#^checkout/successful-payment(?:/([^/]+))?$#', $identifier, $matches)) {
            return null;
        }

        if (!empty($matches[1])) {
            $request->setParam('order_id', $matches[1]);
        }

        $request->setPathInfo('/checkout/successfulpayment/index');
        $request->setModuleName('checkout')
            ->setControllerName('successfulpayment')
            ->setActionName('index')
            ->setRouteName('checkout');

        return $this->actionFactory->create(Forward::class);
    }
}
