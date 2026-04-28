<?php
declare(strict_types=1);

namespace PeakGear\Cart\Controller\Select;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart as CheckoutCart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Model\Quote\Item\Option\Factory as OptionFactory;
use Psr\Log\LoggerInterface;

/**
 * GET cart/select/restore
 *
 * Called automatically by an observer when the user loads the cart page after
 * returning from (or abandoning) checkout.  Reads the snapshot saved by Apply
 * and re-adds those items to the active quote, then clears the snapshot.
 */
class Restore implements HttpGetActionInterface
{
    private const SESSION_KEY = 'peakgear_restored_items';

    public function __construct(
        private readonly SessionManagerInterface    $session,
        private readonly CheckoutSession            $checkoutSession,
        private readonly CheckoutCart               $cart,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly RedirectFactory            $redirectFactory,
        private readonly LoggerInterface            $logger
    ) {
    }

    public function execute(): Redirect
    {
        $this->restoreItems();

        $redirect = $this->redirectFactory->create();
        $redirect->setPath('checkout/cart');
        return $redirect;
    }

    /**
     * Public so it can also be called from an observer/plugin without HTTP redirect.
     */
    public function restoreItems(): void
    {
        $savedItems = $this->session->getData(self::SESSION_KEY);

        if (empty($savedItems) || !is_array($savedItems)) {
            return;
        }

        // Clear first to avoid double-restore
        $this->session->setData(self::SESSION_KEY, []);

        foreach ($savedItems as $itemData) {
            try {
                $sku = $itemData['sku'] ?? '';
                $qty = (float) ($itemData['qty'] ?? 1);

                if (!$sku || $qty <= 0) {
                    continue;
                }

                $product = $this->productRepository->get($sku);
                $buyRequest = new \Magento\Framework\DataObject($itemData['options'] ?? []);
                $buyRequest->setQty($qty);

                $this->cart->addProduct($product, $buyRequest);
            } catch (NoSuchEntityException $e) {
                $this->logger->debug('CartSelect Restore – product not found: ' . ($itemData['sku'] ?? '?'));
            } catch (LocalizedException $e) {
                $this->logger->warning('CartSelect Restore – LocalizedException: ' . $e->getMessage());
            } catch (\Throwable $e) {
                $this->logger->error('CartSelect Restore – unexpected error: ' . $e->getMessage());
            }
        }

        try {
            $this->cart->save();
        } catch (\Throwable $e) {
            $this->logger->error('CartSelect Restore – save error: ' . $e->getMessage());
        }
    }
}
