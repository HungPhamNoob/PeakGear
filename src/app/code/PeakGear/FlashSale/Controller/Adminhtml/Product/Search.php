<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Controller\Adminhtml\Product;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class Search extends Action
{
    public const ADMIN_RESOURCE = 'PeakGear_FlashSale::flash_sale';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ProductCollectionFactory $productCollectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $query = trim((string)$this->getRequest()->getParam('q', ''));
        $result = $this->jsonFactory->create();

        if (mb_strlen($query) < 2) {
            return $result->setData(['items' => []]);
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'sku', 'price'])
            ->addAttributeToFilter([
                ['attribute' => 'name', 'like' => '%' . $query . '%'],
                ['attribute' => 'sku', 'like' => '%' . $query . '%'],
                ['attribute' => 'entity_id', 'eq' => ctype_digit($query) ? (int)$query : 0],
            ])
            ->setPageSize(12)
            ->setCurPage(1)
            ->setOrder('entity_id', 'DESC');

        $items = [];
        foreach ($collection as $product) {
            $items[] = [
                'id' => (int)$product->getId(),
                'sku' => (string)$product->getSku(),
                'name' => (string)$product->getName(),
                'label' => sprintf('#%d - %s (%s)', (int)$product->getId(), (string)$product->getName(), (string)$product->getSku()),
            ];
        }

        return $result->setData(['items' => $items]);
    }
}
