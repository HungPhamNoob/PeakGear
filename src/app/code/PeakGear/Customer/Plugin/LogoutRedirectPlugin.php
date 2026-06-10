<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Customer\Controller\Account\Logout;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\UrlInterface;

class LogoutRedirectPlugin
{
    public function __construct(
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function afterExecute(Logout $subject, Redirect $result): Redirect
    {
        return $result->setUrl($this->urlBuilder->getUrl(''));
    }
}
