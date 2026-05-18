<?php
declare(strict_types=1);

namespace PeakGear\Customer\Model\GuestAccess;

use Magento\Framework\App\RequestInterface;

class RequestClassifier
{
    public function isAsync(RequestInterface $request): bool
    {
        if (method_exists($request, 'isAjax') && $request->isAjax()) {
            return true;
        }

        $requestedWith = method_exists($request, 'getHeader')
            ? (string)$request->getHeader('X-Requested-With')
            : '';
        if (strcasecmp($requestedWith, 'XMLHttpRequest') === 0) {
            return true;
        }

        $contentType = strtolower(method_exists($request, 'getHeader')
            ? (string)$request->getHeader('Content-Type')
            : '');
        if (str_contains($contentType, 'json')) {
            return true;
        }

        $accept = strtolower(method_exists($request, 'getHeader')
            ? (string)$request->getHeader('Accept')
            : '');

        return str_contains($accept, 'application/json')
            || str_contains($accept, 'text/json');
    }
}
