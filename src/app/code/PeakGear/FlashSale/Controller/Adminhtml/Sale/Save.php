<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Controller\Adminhtml\Sale;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PeakGear\FlashSale\Model\ItemFactory;
use PeakGear\FlashSale\Model\ResourceModel\Item as ItemResource;
use PeakGear\FlashSale\Model\ResourceModel\Sale as SaleResource;
use PeakGear\FlashSale\Model\SaleFactory;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'PeakGear_FlashSale::flash_sale';

    public function __construct(
        Context $context,
        private readonly SaleFactory $saleFactory,
        private readonly SaleResource $saleResource,
        private readonly ItemFactory $itemFactory,
        private readonly ItemResource $itemResource,
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TimezoneInterface $timezone
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->_redirect('*/*/index');
        }

        $saleId = (int)$request->getParam('sale_id');
        try {
            $sale = $this->saleFactory->create();
            if ($saleId > 0) {
                $this->saleResource->load($sale, $saleId);
            }

            $sale->addData([
                'title' => trim((string)$request->getParam('title', 'Flash Sale')),
                'is_active' => (int)$request->getParam('is_active', 0),
                'start_at' => $this->normalizeDate((string)$request->getParam('start_at')),
                'end_at' => $this->normalizeDate((string)$request->getParam('end_at')),
            ]);
            $this->saleResource->save($sale);
            $this->replaceItems((int)$sale->getId(), (array)$request->getParam('items', []));

            $this->messageManager->addSuccessMessage(__('Flash sale đã được lưu.'));
            return $this->_redirect('*/*/edit', ['sale_id' => (int)$sale->getId()]);
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('Không thể lưu flash sale: %1', $exception->getMessage()));
            return $this->_redirect('*/*/edit', ['sale_id' => $saleId]);
        }
    }

    private function replaceItems(int $saleId, array $items): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('peakgear_flash_sale_item');
        $connection->delete($table, ['sale_id = ?' => $saleId]);

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }

            $productId = $this->resolveProductId((string)($row['product'] ?? ''));
            if ($productId <= 0) {
                continue;
            }

            $item = $this->itemFactory->create();
            $item->addData([
                'sale_id' => $saleId,
                'product_id' => $productId,
                'discount_percent' => min(100, max(0, (float)($row['discount_percent'] ?? 0))),
                'qty_limit' => max(0, (int)($row['qty_limit'] ?? 0)),
                'sold_qty' => max(0, (int)($row['sold_qty'] ?? 0)),
                'max_per_customer' => max(0, (int)($row['max_per_customer'] ?? 0)),
                'max_per_order' => max(0, (int)($row['max_per_order'] ?? 0)),
            ]);
            $this->itemResource->save($item);
        }
    }

    private function resolveProductId(string $product): int
    {
        $product = trim($product);
        if ($product === '') {
            return 0;
        }
        if (ctype_digit($product)) {
            return (int)$product;
        }

        try {
            return (int)$this->productRepository->get($product)->getId();
        } catch (NoSuchEntityException) {
            return 0;
        }
    }

    private function normalizeDate(string $value): string
    {
        $value = trim(str_replace('T', ' ', $value));
        if ($value === '') {
            throw new \InvalidArgumentException('Thời gian không hợp lệ.');
        }

        $timezone = new \DateTimeZone($this->timezone->getConfigTimezone());
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value, $timezone)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);

        if (!$date) {
            throw new \InvalidArgumentException('Thời gian không hợp lệ.');
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
