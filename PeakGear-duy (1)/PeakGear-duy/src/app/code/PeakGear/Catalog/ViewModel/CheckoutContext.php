<?php
declare(strict_types=1);

namespace PeakGear\Catalog\ViewModel;

use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Exposes lightweight customer auth state to theme templates.
 */
class CheckoutContext implements ArgumentInterface
{
    public function __construct(
        private readonly Context $httpContext
    ) {
    }

    public function isLoggedIn(): bool
    {
        return (bool)$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }
}
