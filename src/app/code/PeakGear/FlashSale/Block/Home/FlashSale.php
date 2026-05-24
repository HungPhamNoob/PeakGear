<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Block\Home;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PeakGear\FlashSale\Model\FlashSaleService;
use PeakGear\FlashSale\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;

class FlashSale extends AbstractProduct
{
    private const UPCOMING_WINDOW_SECONDS = 259200;

    public function __construct(
        Context $context,
        private readonly ItemCollectionFactory $itemCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly FlashSaleService $flashSaleService,
        private readonly DateTime $dateTime,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSaleGroups(): array
    {
        $now = new \DateTimeImmutable($this->dateTime->gmtDate(), new \DateTimeZone('UTC'));
        $until = $now->modify('+' . self::UPCOMING_WINDOW_SECONDS . ' seconds');

        $collection = $this->itemCollectionFactory->create();
        $collection->getSelect()
            ->join(
                ['sale' => $collection->getTable('peakgear_flash_sale')],
                'main_table.sale_id = sale.sale_id',
                ['title', 'start_at', 'end_at']
            )
            ->where('sale.is_active = ?', 1)
            ->where('sale.end_at > ?', $now->format('Y-m-d H:i:s'))
            ->where('sale.start_at <= ?', $until->format('Y-m-d H:i:s'))
            ->where('(main_table.qty_limit - main_table.sold_qty) > 0')
            ->order('sale.start_at ASC')
            ->order('main_table.discount_percent DESC');

        $groups = [];
        foreach ($collection as $item) {
            $saleId = (int)$item->getData('sale_id');
            if (!isset($groups[$saleId])) {
                $startAt = new \DateTimeImmutable((string)$item->getData('start_at'), new \DateTimeZone('UTC'));
                $endAt = new \DateTimeImmutable((string)$item->getData('end_at'), new \DateTimeZone('UTC'));
                $isUpcoming = $startAt > $now;
                $groups[$saleId] = [
                    'sale_id' => $saleId,
                    'title' => (string)$item->getData('title'),
                    'start_at' => (string)$item->getData('start_at'),
                    'end_at' => (string)$item->getData('end_at'),
                    'status' => $isUpcoming ? 'upcoming' : 'active',
                    'status_label' => $isUpcoming ? 'Sắp diễn ra' : 'Đang diễn ra',
                    'countdown_label' => $isUpcoming ? 'Bắt đầu sau' : 'Kết thúc sau',
                    'countdown_to' => ($isUpcoming ? $startAt : $endAt)->format(DATE_ATOM),
                    'display_start_at' => $this->formatStoreDate($startAt),
                    'display_end_at' => $this->formatStoreDate($endAt),
                    'items' => [],
                ];
            }

            try {
                $product = $this->productRepository->getById((int)$item->getData('product_id'));
            } catch (\Throwable) {
                continue;
            }

            if (!$product->isVisibleInCatalog()) {
                continue;
            }

            $regularPrice = (float)$product->getPrice();
            $salePrice = $this->flashSaleService->getDiscountedPrice($product, $item, $regularPrice);
            $groups[$saleId]['items'][] = [
                'item' => $item,
                'product' => $product,
                'name' => $product->getName(),
                'url' => $product->getProductUrl(),
                'image_url' => $this->getImage($product, 'category_page_grid')->getImageUrl(),
                'regular_price' => $regularPrice,
                'sale_price' => $salePrice,
                'discount_percent' => (float)$item->getData('discount_percent'),
                'remaining_qty' => $item->getRemainingQty(),
            ];
        }

        return array_values(array_filter($groups, static fn(array $group): bool => !empty($group['items'])));
    }

    public function formatPrice(float $price): string
    {
        return number_format((int) round($price), 0, '.', ',') . '₫';
    }

    private function formatStoreDate(\DateTimeImmutable $date): string
    {
        return $date
            ->setTimezone(new \DateTimeZone($this->timezone->getConfigTimezone()))
            ->format('d/m/Y H:i');
    }
}
