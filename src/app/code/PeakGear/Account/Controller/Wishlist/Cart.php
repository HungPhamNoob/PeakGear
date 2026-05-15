<?php
declare(strict_types=1);

namespace PeakGear\Account\Controller\Wishlist;

use Magento\Catalog\Helper\Product;
use Magento\Catalog\Model\Product\Exception as ProductException;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Checkout\Model\Cart as CheckoutCart;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Wishlist\Controller\AbstractIndex;
use Magento\Wishlist\Controller\WishlistProviderInterface;
use Magento\Wishlist\Helper\Data;
use Magento\Wishlist\Model\ItemFactory;
use Magento\Wishlist\Model\LocaleQuantityProcessor;
use Magento\Wishlist\Model\ResourceModel\Item\Option\Collection;
use Magento\Wishlist\Model\Item\OptionFactory;

class Cart extends AbstractIndex implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly WishlistProviderInterface $wishlistProvider,
        private readonly LocaleQuantityProcessor $quantityProcessor,
        private readonly ItemFactory $itemFactory,
        private readonly CheckoutCart $cart,
        private readonly OptionFactory $optionFactory,
        private readonly Product $productHelper,
        private readonly Data $wishlistHelper,
        private readonly CartHelper $cartHelper,
        private readonly Validator $formKeyValidator
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $resultJson->setData([
                'success' => false,
                'message' => (string)__('Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.'),
            ]);
        }

        $itemId = (int)$this->getRequest()->getParam('item');
        $item = $this->itemFactory->create()->load($itemId);

        if (!$item->getId()) {
            return $resultJson->setData([
                'success' => false,
                'message' => (string)__('Không tìm thấy sản phẩm yêu thích.'),
            ]);
        }

        $wishlist = $this->wishlistProvider->getWishlist($item->getWishlistId());
        if (!$wishlist || !$wishlist->getId()) {
            return $resultJson->setData([
                'success' => false,
                'message' => (string)__('Không tìm thấy danh sách yêu thích.'),
            ]);
        }

        $qty = $this->resolveQty($itemId);
        if ($qty) {
            $item->setQty($qty);
        }

        $configureUrl = $this->_url->getUrl(
            'wishlist/index/configure',
            [
                'id' => $item->getId(),
                'product_id' => $item->getProductId(),
            ]
        );

        $redirectUrl = $this->getRequest()->getHeader('X-Requested-With') === 'XMLHttpRequest'
            ? ''
            : $this->cartHelper->getCartUrl();

        try {
            /** @var Collection $options */
            $options = $this->optionFactory->create()->getCollection()->addItemFilter([$itemId]);
            $item->setOptions($options->getOptionsByItem($itemId));

            $buyRequest = $this->productHelper->addParamsToBuyRequest(
                $this->getRequest()->getParams(),
                ['current_config' => $item->getBuyRequest()]
            );

            $item->mergeBuyRequest($buyRequest);
            $item->addToCart($this->cart, false);

            $related = (string)$this->getRequest()->getParam('related_product', '');
            if ($related !== '') {
                $this->cart->addProductsByIds(explode(',', $related));
            }

            $this->cart->save()->getQuote()->collectTotals();
            $wishlist->save();
            $this->wishlistHelper->calculate();

            if ($this->cart->getQuote()->getHasError()) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => (string)__('Không thể thêm sản phẩm vào giỏ hàng lúc này.'),
                ]);
            }

            return $resultJson->setData([
                'success' => true,
                'message' => (string)__('Đã thêm %1 vào giỏ hàng.', $item->getProduct()->getName()),
            ]);
        } catch (ProductException) {
            return $resultJson->setData([
                'success' => false,
                'message' => (string)__('Sản phẩm hiện đã hết hàng.'),
            ]);
        } catch (LocalizedException $exception) {
            return $resultJson->setData([
                'success' => false,
                'message' => $exception->getMessage(),
                'redirectUrl' => $configureUrl,
            ]);
        } catch (\Throwable $exception) {
            return $resultJson->setData([
                'success' => false,
                'message' => (string)__('Không thể thêm sản phẩm vào giỏ hàng lúc này.'),
                'redirectUrl' => $redirectUrl,
            ]);
        }
    }

    private function resolveQty(int $itemId): float|int|string|null
    {
        $qty = $this->getRequest()->getParam('qty');
        $postQty = $this->getRequest()->getPostValue('qty');

        if ($postQty !== null && $qty !== $postQty) {
            $qty = $postQty;
        }

        if (is_array($qty)) {
            $qty = $qty[$itemId] ?? 1;
        }

        return $this->quantityProcessor->process($qty);
    }
}
