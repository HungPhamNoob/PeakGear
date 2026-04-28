<?php
declare(strict_types=1);

namespace PeakGear\Cart\ViewModel;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CartItemQuantity implements ArgumentInterface
{
    private const DEFAULT_MIN_QTY = 1.0;

    public function __construct(
        private readonly StockRegistryInterface $stockRegistry,
        private readonly StockResolverInterface $stockResolver,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Return quantity config for a cart line item.
     *
     * @param AbstractItem $item
     * @return array<string, float|int|null|bool>
     */
    public function getConfig(AbstractItem $item): array
    {
        $product = $item->getProduct();
        $currentQty = (float) $item->getQty();

        $config = [
            'currentQty' => $currentQty,
            'minAllowed' => self::DEFAULT_MIN_QTY,
            'maxAllowed' => null,
            'qtyIncrements' => null,
            'isSoldOut' => false,
        ];

        if (!$product || !$product->getId()) {
            return $config;
        }

        try {
            $websiteId = (int) $product->getStore()->getWebsiteId();
            $stockItem = $this->stockRegistry->getStockItem((int) $product->getId(), $websiteId);

            if ($stockItem) {
                $minSaleQty = (float) $stockItem->getMinSaleQty();
                $qtyIncrements = (float) $stockItem->getQtyIncrements();
                $maxSaleQty = (float) $stockItem->getMaxSaleQty();

                $config['minAllowed'] = max(self::DEFAULT_MIN_QTY, $minSaleQty);
                if ($qtyIncrements > 0) {
                    $config['qtyIncrements'] = $qtyIncrements;
                }

                if ($stockItem->getManageStock()) {
                    $availableQty = $this->getAvailableQty($item->getSku(), $websiteId);
                    if ($availableQty === null) {
                        $availableQty = (float) $stockItem->getQty();
                    }

                    if ($maxSaleQty > 0) {
                        $availableQty = min($availableQty, $maxSaleQty);
                    }

                    $config['maxAllowed'] = max(0.0, $availableQty);
                    $config['isSoldOut'] = $config['maxAllowed'] <= 0.0;
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->debug('Cart quantity config could not be resolved.', [
                'exception' => $exception,
                'item_id' => $item->getId(),
                'product_id' => $product->getId(),
            ]);
        }

        return $config;
    }

    /**
     * Resolve salable quantity for the current website.
     */
    private function getAvailableQty(string $sku, int $websiteId): ?float
    {
        try {
            $website = $this->storeManager->getWebsite($websiteId);
            $stockId = (int) $this->stockResolver->execute(
                SalesChannelInterface::TYPE_WEBSITE,
                (string) $website->getCode()
            )->getStockId();

            return $this->getProductSalableQty->execute($sku, $stockId);
        } catch (NoSuchEntityException $exception) {
            $this->logger->debug('Unable to resolve stock for cart item quantity.', [
                'sku' => $sku,
                'website_id' => $websiteId,
                'exception' => $exception,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->debug('Unable to load salable qty for cart item.', [
                'sku' => $sku,
                'website_id' => $websiteId,
                'exception' => $exception,
            ]);
        }

        return null;
    }
}
