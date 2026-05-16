<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Controller\Adminhtml\Sale;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use PeakGear\FlashSale\Model\SaleFactory;
use PeakGear\FlashSale\Model\ResourceModel\Sale as SaleResource;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'PeakGear_FlashSale::flash_sale';

    public function __construct(
        Context $context,
        private readonly SaleFactory $saleFactory,
        private readonly SaleResource $saleResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $saleId = (int)$this->getRequest()->getParam('sale_id');
        if ($saleId > 0) {
            try {
                $sale = $this->saleFactory->create();
                $this->saleResource->load($sale, $saleId);
                if ($sale->getId()) {
                    $this->saleResource->delete($sale);
                    $this->messageManager->addSuccessMessage(__('Flash sale đã được xóa.'));
                }
            } catch (\Throwable $exception) {
                $this->messageManager->addErrorMessage(__('Không thể xóa flash sale: %1', $exception->getMessage()));
            }
        }

        return $this->_redirect('*/*/index');
    }
}
