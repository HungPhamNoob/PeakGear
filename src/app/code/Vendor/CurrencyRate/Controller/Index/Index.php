<?php
declare(strict_types=1);

namespace Vendor\CurrencyRate\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private PageFactory $pageFactory
    ) {}

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Tỷ giá ngoại tệ - Vietcombank'));
        return $page;
    }
}
