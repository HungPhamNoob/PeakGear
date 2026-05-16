<?php
declare(strict_types=1);

namespace PeakGear\FlashSale\Controller\Adminhtml\Sale;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'PeakGear_FlashSale::flash_sale';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('PeakGear_FlashSale::flash_sale');
        $page->getConfig()->getTitle()->prepend(__('Edit Flash Sale'));
        return $page;
    }
}
