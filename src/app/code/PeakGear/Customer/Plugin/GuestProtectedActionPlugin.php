<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Checkout\Controller\Index\Index as CheckoutIndex;
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
            default => 'Bạn cần đăng nhập để tiếp tục.',
        };
    }
}
