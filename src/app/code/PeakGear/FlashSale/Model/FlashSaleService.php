<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PeakGear\FlashSale\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;

class FlashSaleService
{
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

    public function validateQty(int $productId, float $requestedQty, ?int $customerId = null, ?string $customerEmail = null): ?string
    {
        $item = $this->getActiveItemForProduct($productId);
        if (!$item) {
            return null;
        }

        $requestedQty = (int)ceil($requestedQty);
        $remainingQty = $item->getRemainingQty();
        if ($requestedQty > $remainingQty) {
            return (string)__('Flash sale chỉ còn %1 sản phẩm.', $remainingQty);
        }

        $maxPerOrder = (int)$item->getData('max_per_order');
        if ($maxPerOrder > 0 && $requestedQty > $maxPerOrder) {
            return (string)__('Mỗi đơn hàng chỉ được mua tối đa %1 sản phẩm flash sale này.', $maxPerOrder);
        }

        $maxPerCustomer = (int)$item->getData('max_per_customer');
        if ($maxPerCustomer > 0) {
            $orderedQty = $this->getCustomerOrderedQty($productId, (int)$item->getData('sale_id'), $customerId, $customerEmail);
            if ($orderedQty + $requestedQty > $maxPerCustomer) {
                $left = max(0, $maxPerCustomer - $orderedQty);
                return (string)__('Bạn chỉ còn được mua %1 sản phẩm trong flash sale này.', $left);
            }
        }

        return null;
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

    private function getCustomerOrderedQty(int $productId, int $saleId, ?int $customerId, ?string $customerEmail): int
    {
        if (!$customerId && !$customerEmail) {
            return 0;
        }

        $connection = $this->resourceConnection->getConnection();
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $saleTable = $this->resourceConnection->getTableName('peakgear_flash_sale');

        $select = $connection->select()
            ->from(['oi' => $orderItemTable], ['qty' => 'SUM(oi.qty_ordered)'])
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

        return (int)$connection->fetchOne($select);
    }
}
