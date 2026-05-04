<?php
declare(strict_types=1);

namespace PeakGear\Cart\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use PeakGear\Cart\Controller\Select\Restore;

/**
 * Observer: controller_action_predispatch_checkout_cart_index
 *
 * When the user navigates to the cart page, check if there are items that were
 * temporarily removed by the CartSelect\Apply controller and restore them.
 */
class RestoreDeselectedItemsOnCart implements ObserverInterface
{
    public function __construct(
        private readonly Restore $restoreAction
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->restoreAction->restoreItems();
    }
}
