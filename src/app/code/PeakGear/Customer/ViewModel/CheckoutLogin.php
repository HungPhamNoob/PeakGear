<?php
declare(strict_types=1);

namespace PeakGear\Customer\ViewModel;

use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CheckoutLogin implements ArgumentInterface
{
    public function __construct(
        private readonly Context $httpContext,
        private readonly UrlInterface $urlBuilder,
        private readonly EncoderInterface $urlEncoder
    ) {
    }

    public function isLoggedIn(): bool
    {
        return (bool) $this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }

    public function getLoginUrl(): string
    {
        return $this->urlBuilder->getUrl('customer/account/login', [
            'referer' => $this->urlEncoder->encode($this->urlBuilder->getUrl('checkout')),
        ]);
    }
}
