<?php
declare(strict_types=1);

namespace PeakGear\Cart\Controller\Select;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * POST cart/select/apply
 *
 * Triggered by the hidden form in select-init.phtml when the user clicks
 * "Proceed to Checkout" with at least one item deselected.
 *
 * 1. Reads the comma-separated list of deselected quote item IDs.
 * 2. Snapshots each item (sku, qty, options) into the session so they can be
 *    restored when the user returns to the cart.
 * 3. Removes those items from the active quote and saves it.
 * 4. Redirects the browser to /checkout.
 *
 * form_key validation is handled automatically by Magento because this
 * implements HttpPostActionInterface and the form includes a form_key input.
 */
class Apply implements HttpPostActionInterface
{
    private const SESSION_KEY = 'peakgear_restored_items';

    public function __construct(
        private readonly RequestInterface        $request,
        private readonly RedirectFactory         $redirectFactory,
        private readonly CheckoutSession         $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly SessionManagerInterface $session,
        private readonly LoggerInterface         $logger
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();

        $raw = (string) $this->request->getParam('deselected_ids', '');
        $deselectedIds = array_values(array_filter(
            array_map('intval', explode(',', $raw)),
            fn(int $id) => $id > 0
        ));

        if (!empty($deselectedIds)) {
            try {
                $this->removeItems($deselectedIds);
            } catch (\Throwable $e) {
                $this->logger->critical('PeakGear CartSelect Apply: ' . $e->getMessage());
            }
        }

        // Always redirect to checkout — items were either removed or the error
        // was non-fatal (user lands on checkout with whatever is left in quote).
        $redirect->setPath('checkout');
        return $redirect;
    }

    /**
     * Remove selected items from the quote and persist them for later restore.
     *
     * @param int[] $deselectedIds Quote item IDs to remove.
     * @throws LocalizedException
     */
    private function removeItems(array $deselectedIds): void
    {
        $quote = $this->checkoutSession->getQuote();
        $savedItems = [];

        foreach ($deselectedIds as $itemId) {
            $item = $quote->getItemById($itemId);
            if (!$item) {
                continue;
            }

            // Snapshot so we can restore later
            $savedItems[] = [
                'sku'        => $item->getSku(),
                'product_id' => (int) $item->getProductId(),
                'qty'        => (float) $item->getQty(),
                'options'    => $item->getBuyRequest()->toArray(),
            ];

            $quote->removeItem($itemId);
        }

        if (empty($savedItems)) {
            return;
        }

        // Merge with any previously stored items (e.g. user hit back, deselected again)
        $existing = $this->session->getData(self::SESSION_KEY);
        if (!is_array($existing)) {
            $existing = [];
        }
        $this->session->setData(self::SESSION_KEY, array_merge($existing, $savedItems));

        $this->cartRepository->save($quote);
    }
}
