<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PeakGear\FlashSale\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;

class FlashSaleService
{
    public const DISCOUNT_MARKER_PREFIX = 'peakgear_flash_sale_item_id:';
    public const REGULAR_PRICE_MARKER = 'peakgear_flash_sale_regular_price';

    private array $activeItemCache = [];

    public function __construct(
        private readonly ItemCollectionFactory $itemCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly DateTime $dateTime
    ) {
    }

    public function getActiveItemForProduct(int $productId): ?Item
    {
        if (array_key_exists($productId, $this->activeItemCache)) {
            return $this->activeItemCache[$productId];
        }

        $now = $this->dateTime->gmtDate();
        $collection = $this->itemCollectionFactory->create();
        $collection->getSelect()
            ->join(
                ['sale' => $collection->getTable('peakgear_flash_sale')],
                'main_table.sale_id = sale.sale_id',
                ['title', 'start_at', 'end_at']
            )
            ->where('main_table.product_id = ?', $productId)
            ->where('sale.is_active = ?', 1)
            ->where('sale.start_at <= ?', $now)
            ->where('sale.end_at > ?', $now)
            ->where('(main_table.qty_limit - main_table.sold_qty) > 0')
            ->order('main_table.discount_percent DESC')
            ->limit(1);

        $item = $collection->getFirstItem();
        $this->activeItemCache[$productId] = $item->getId() ? $item : null;
        return $this->activeItemCache[$productId];
    }

    public function getDiscountedPrice(ProductInterface $product, Item $item, ?float $basePrice = null): float
    {
        $price = $basePrice ?? (float)$product->getPrice();
        $discountPercent = min(100.0, max(0.0, (float)$item->getData('discount_percent')));
        return round($price * (100.0 - $discountPercent) / 100.0, 4);
    }

    public function hasReachedCustomerLimit(
        int $productId,
        Item $item,
        ?int $customerId = null,
        ?string $customerEmail = null
    ): bool {
        $maxPerCustomer = (int)$item->getData('max_per_customer');
        if ($maxPerCustomer <= 0) {
            return false;
        }

        return $this->getCustomerOrderedQty(
            $productId,
            (int)$item->getData('sale_id'),
            (int)$item->getId(),
            $customerId,
            $customerEmail
        ) >= $maxPerCustomer;
    }

    public function getEligibleDiscountQty(
        int $productId,
        Item $item,
        float $requestedQty,
        ?int $customerId = null,
        ?string $customerEmail = null
    ): int {
        $eligibleQty = min((int)ceil($requestedQty), $item->getRemainingQty());
        $maxPerOrder = (int)$item->getData('max_per_order');
        if ($maxPerOrder > 0) {
            $eligibleQty = min($eligibleQty, $maxPerOrder);
        }

        $maxPerCustomer = (int)$item->getData('max_per_customer');
        if ($maxPerCustomer > 0) {
            $orderedQty = $this->getCustomerOrderedQty(
                $productId,
                (int)$item->getData('sale_id'),
                (int)$item->getId(),
                $customerId,
                $customerEmail
            );
            $eligibleQty = min($eligibleQty, max(0, $maxPerCustomer - $orderedQty));
        }

        return max(0, $eligibleQty);
    }

    public function getBlendedUnitPrice(
        ProductInterface $product,
        Item $item,
        float $requestedQty,
        int $discountedQty
    ): float {
        $requestedQty = max(1, (int)ceil($requestedQty));
        $discountedQty = min($requestedQty, max(0, $discountedQty));
        $regularPrice = (float)$product->getPrice();
        $discountedPrice = $this->getDiscountedPrice($product, $item, $regularPrice);
        $rowTotal = ($discountedQty * $discountedPrice)
            + (($requestedQty - $discountedQty) * $regularPrice);

        return round($rowTotal / $requestedQty, 4);
    }

    public function validateQty(int $productId, float $requestedQty, ?int $customerId = null, ?string $customerEmail = null): ?string
    {
        return null;
    }

    public function getDiscountMarker(int $itemId, int $discountedQty): string
    {
        return self::DISCOUNT_MARKER_PREFIX . $itemId . ':' . max(0, $discountedQty);
    }

    public function getMarkedItemId(?string $additionalData): ?int
    {
        if (!$additionalData || !str_starts_with($additionalData, self::DISCOUNT_MARKER_PREFIX)) {
            return null;
        }

        $marker = substr($additionalData, strlen(self::DISCOUNT_MARKER_PREFIX));
        $itemId = (int)explode(':', $marker, 2)[0];
        return $itemId > 0 ? $itemId : null;
    }

    public function getMarkedDiscountQty(?string $additionalData, int $fallbackQty = 0): int
    {
        if ($this->getMarkedItemId($additionalData) === null) {
            return 0;
        }

        $marker = substr((string)$additionalData, strlen(self::DISCOUNT_MARKER_PREFIX));
        $parts = explode(':', $marker, 2);
        return isset($parts[1]) ? max(0, (int)$parts[1]) : max(0, $fallbackQty);
    }

    public function isRegularPriceMarker(?string $additionalData): bool
    {
        return $additionalData === self::REGULAR_PRICE_MARKER;
    }

    public function incrementSoldQty(int $itemId, int $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('peakgear_flash_sale_item');
        $connection->update(
            $table,
            ['sold_qty' => new \Zend_Db_Expr('LEAST(qty_limit, sold_qty + ' . (int)$qty . ')')],
            ['item_id = ?' => $itemId]
        );
    }

    private function getCustomerOrderedQty(
        int $productId,
        int $saleId,
        int $itemId,
        ?int $customerId,
        ?string $customerEmail
    ): int
    {
        if (!$customerId && !$customerEmail) {
            return 0;
        }

        $connection = $this->resourceConnection->getConnection();
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $saleTable = $this->resourceConnection->getTableName('peakgear_flash_sale');

        $select = $connection->select()
            ->from(['oi' => $orderItemTable], ['qty_ordered', 'additional_data'])
            ->join(['o' => $orderTable], 'oi.order_id = o.entity_id', [])
            ->join(['s' => $saleTable], 's.sale_id = ' . (int)$saleId, [])
            ->where('oi.product_id = ?', $productId)
            ->where('o.created_at >= s.start_at')
            ->where('o.created_at < s.end_at')
            ->where('o.state NOT IN (?)', ['canceled', 'closed']);

        if ($customerId) {
            $select->where('o.customer_id = ?', $customerId);
        } else {
            $select->where('o.customer_email = ?', $customerEmail);
        }

        $discountedQty = 0;
        foreach ($connection->fetchAll($select) as $row) {
            if ($this->getMarkedItemId($row['additional_data'] ?? null) !== $itemId) {
                continue;
            }
            $discountedQty += $this->getMarkedDiscountQty(
                $row['additional_data'] ?? null,
                (int)ceil((float)($row['qty_ordered'] ?? 0))
            );
        }

        return $discountedQty;
    }
}
