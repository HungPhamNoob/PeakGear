<?php
declare(strict_types=1);

namespace PeakGear\Cart\Controller\Buynow;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart as CheckoutCart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use PeakGear\Cart\Model\BuyNowSession;
use PeakGear\Customer\Model\GuestAccess\DeniedResultFactory;
use Psr\Log\LoggerInterface;

class Apply implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CheckoutCart $cart,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly BuyNowSession $buyNowSession,
        private readonly LoggerInterface $logger,
        private readonly CustomerSession $customerSession,
        private readonly DeniedResultFactory $deniedResultFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): Redirect
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->deniedResultFactory->createRedirectResult(
                'Bạn cần đăng nhập để tiếp tục thanh toán.',
                $this->request
            );
        }

        $redirect = $this->redirectFactory->create();

        try {
            $this->applyBuyNow();
            $redirect->setPath('checkout');
            return $redirect;
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->warning('PeakGear BuyNow Apply: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Không thể xử lý Mua ngay lúc này.'));
            $this->logger->critical('PeakGear BuyNow Apply: ' . $e->getMessage());
        }

        $backUrl = $this->getSafeBackUrl();
        if ($backUrl === null) {
            $redirect->setPath('checkout/cart');
        } else {
            $redirect->setUrl($backUrl);
        }
        return $redirect;
    }

    /**
     * Replace the active quote contents with a single PDP product and keep a
     * snapshot of the previous cart so it can be restored later.
     *
     * @throws LocalizedException
     */
    private function applyBuyNow(): void
    {
        $quote = $this->checkoutSession->getQuote();
        $productId = (int) $this->request->getParam('product');

        if ($productId <= 0) {
            throw new LocalizedException(__('Sản phẩm không hợp lệ.'));
        }

        $snapshotItems = $this->snapshotQuoteItems($quote);

        try {
            $product = $this->productRepository->getById($productId);
            $quote->removeAllItems();

            $buyRequest = new DataObject($this->buildBuyRequestData());
            $this->cart->addProduct($product, $buyRequest);
            $this->cart->save();
            $this->resetQuoteCheckoutState($quote);
            $quote->collectTotals();
            $quote->save();

            $temporaryQuoteId = (int) $quote->getId();
            if ($temporaryQuoteId > 0) {
                $this->buyNowSession->rememberOriginalCart($snapshotItems, $temporaryQuoteId);
            }
        } catch (\Throwable $e) {
            $this->buyNowSession->clear();
            $quote->removeAllItems();
            $this->restoreSnapshotItems($snapshotItems);
            $this->cart->save();

            if ($e instanceof LocalizedException) {
                throw $e;
            }

            throw new LocalizedException(__('Không thể xử lý Mua ngay lúc này.'), $e);
        }
    }

    private function buildBuyRequestData(): array
    {
        $params = $this->request->getParams();

        unset(
            $params['form_key'],
            $params['uenc'],
            $params['return_url']
        );

        return $params;
    }

    /**
     * Buy now should always restart checkout from shipping instead of reusing
     * any previous shipping/payment state left on the active quote.
     */
    private function resetQuoteCheckoutState(\Magento\Quote\Model\Quote $quote): void
    {
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress = $quote->getBillingAddress();
        $payment = $quote->getPayment();

        if ($shippingAddress) {
            $shippingAddress->setCustomerAddressId(null);
            $shippingAddress->setSameAsBilling(0);
            $shippingAddress->setCollectShippingRates(true);
            $shippingAddress->removeAllShippingRates();
            $shippingAddress->setShippingMethod(null);
            $shippingAddress->setShippingDescription(null);
            $shippingAddress->setCountryId(null);
            $shippingAddress->setRegionId(null);
            $shippingAddress->setRegion(null);
            $shippingAddress->setRegionCode(null);
            $shippingAddress->setCity(null);
            $shippingAddress->setPostcode(null);
            $shippingAddress->setStreet([]);
            $shippingAddress->setTelephone(null);
            $shippingAddress->setCompany(null);
            $shippingAddress->setFirstname(null);
            $shippingAddress->setLastname(null);
            $shippingAddress->setMiddlename(null);
            $shippingAddress->setPrefix(null);
            $shippingAddress->setSuffix(null);
            $shippingAddress->setVatId(null);
            $shippingAddress->setShouldIgnoreValidation(false);
        }

        if ($billingAddress) {
            $billingAddress->setCustomerAddressId(null);
            $billingAddress->setSameAsBilling(0);
            $billingAddress->setCountryId(null);
            $billingAddress->setRegionId(null);
            $billingAddress->setRegion(null);
            $billingAddress->setRegionCode(null);
            $billingAddress->setCity(null);
            $billingAddress->setPostcode(null);
            $billingAddress->setStreet([]);
            $billingAddress->setTelephone(null);
            $billingAddress->setCompany(null);
            $billingAddress->setFirstname(null);
            $billingAddress->setLastname(null);
            $billingAddress->setMiddlename(null);
            $billingAddress->setPrefix(null);
            $billingAddress->setSuffix(null);
            $billingAddress->setVatId(null);
            $billingAddress->setShouldIgnoreValidation(false);
        }

        if ($payment) {
            $payment->setMethod(null);
            $payment->setAdditionalInformation([]);
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->setReservedOrderId(null);
        $this->checkoutSession->setStepData('shipping', 'complete', false);
        $this->checkoutSession->setStepData('billing', 'complete', false);
    }

    private function snapshotQuoteItems(\Magento\Quote\Model\Quote $quote): array
    {
        $savedItems = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $savedItems[] = [
                'sku' => (string) $item->getSku(),
                'product_id' => (int) $item->getProductId(),
                'qty' => (float) $item->getQty(),
                'options' => $item->getBuyRequest() ? $item->getBuyRequest()->toArray() : [],
            ];
        }

        return $savedItems;
    }

    private function restoreSnapshotItems(array $snapshotItems): void
    {
        foreach ($snapshotItems as $itemData) {
            try {
                $sku = $itemData['sku'] ?? '';
                $qty = (float) ($itemData['qty'] ?? 1);

                if ($sku === '' || $qty <= 0) {
                    continue;
                }

                $product = $this->productRepository->get($sku);
                $buyRequest = new DataObject($itemData['options'] ?? []);
                $buyRequest->setQty($qty);

                $this->cart->addProduct($product, $buyRequest);
            } catch (\Throwable $e) {
                $this->logger->error('PeakGear BuyNow rollback failed: ' . $e->getMessage());
            }
        }
    }

    private function getSafeBackUrl(): ?string
    {
        $referer = (string) $this->request->getServer('HTTP_REFERER');

        return $referer !== '' ? $referer : null;
    }
}
