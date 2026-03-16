<?php
declare(strict_types=1);

namespace PeakGear\CartRoute\Model;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;

/**
 * Custom router that maps short /cart URLs to Magento checkout controllers.
 *
 *   /cart           → checkout/cart/index   (cart page)
 *   /cart/checkout  → checkout/index/index  (one-page checkout)
 */
class Router implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory
    ) {
    }

    public function match(RequestInterface $request): ?\Magento\Framework\App\ActionInterface
    {
        if (!$request instanceof HttpRequest) {
            return null;
        }

        $path = trim($request->getPathInfo(), '/');

        // /cart  or  /cart/
        if ($path === 'cart' || $path === 'cart/index' || $path === 'cart/index/index') {
            // Change pathInfo so the standard router processes /checkout/cart/index on re-dispatch.
            // Our router will NOT match again because the new path is "checkout/cart/index".
            $request->setPathInfo('/checkout/cart/index')
                    ->setAlias(
                        \Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS,
                        'cart'
                    );
            return $this->actionFactory->create(Forward::class);
        }

        // /cart/checkout  or  /cart/checkout/
        if ($path === 'cart/checkout' || str_starts_with($path, 'cart/checkout/')) {
            $request->setPathInfo('/checkout/index/index')
                    ->setAlias(
                        \Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS,
                        'cart/checkout'
                    );
            return $this->actionFactory->create(Forward::class);
        }

        return null;
    }
}
