<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Checkout\Controller\Cart\Add;
use Magento\Checkout\Controller\Cart\Addgroup;
use Magento\Checkout\Controller\Cart\Delete;
use Magento\Checkout\Controller\Cart\Index as CartIndex;
use Magento\Checkout\Controller\Cart\UpdateItemOptions;
use Magento\Checkout\Controller\Cart\UpdateItemQty as CartUpdateItemQty;
use Magento\Checkout\Controller\Cart\UpdatePost;
use Magento\Checkout\Controller\Index\Index as CheckoutIndex;
use Magento\Checkout\Controller\Sidebar\RemoveItem;
use Magento\Checkout\Controller\Sidebar\UpdateItemQty as SidebarUpdateItemQty;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Wishlist\Controller\Index\Add as WishlistAdd;
use Magento\Wishlist\Controller\Index\Remove as WishlistRemove;
use PeakGear\Customer\Model\GuestAccess\DeniedResultFactory;

class GuestProtectedActionPlugin
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly DeniedResultFactory $deniedResultFactory
    ) {
    }

    public function aroundExecute(object $subject, callable $proceed)
    {
        if ($this->customerSession->isLoggedIn()) {
            return $proceed();
        }

        if (!method_exists($subject, 'getRequest')) {
            return $proceed();
        }

        $request = $subject->getRequest();
        if (!$request instanceof RequestInterface) {
            return $proceed();
        }

        return $this->deniedResultFactory->createResult($request, $this->resolveMessage($subject));
    }

    private function resolveMessage(object $subject): string
    {
        return match (true) {
            $subject instanceof WishlistAdd,
            $subject instanceof WishlistRemove => 'Bạn cần đăng nhập để sử dụng danh sách yêu thích.',
            $subject instanceof CheckoutIndex => 'Bạn cần đăng nhập để tiếp tục thanh toán.',
            $subject instanceof Add,
            $subject instanceof Addgroup,
            $subject instanceof Delete,
            $subject instanceof CartIndex,
            $subject instanceof UpdateItemOptions,
            $subject instanceof CartUpdateItemQty,
            $subject instanceof UpdatePost,
            $subject instanceof RemoveItem,
            $subject instanceof SidebarUpdateItemQty => 'Bạn cần đăng nhập để sử dụng giỏ hàng.',
            default => 'Bạn cần đăng nhập để tiếp tục.',
        };
    }
}
