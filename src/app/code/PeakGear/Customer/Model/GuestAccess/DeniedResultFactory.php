<?php
declare(strict_types=1);

namespace PeakGear\Customer\Model\GuestAccess;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;

class DeniedResultFactory
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $urlBuilder,
        private readonly EncoderInterface $urlEncoder,
        private readonly RequestClassifier $requestClassifier
    ) {
    }

    public function createResult(RequestInterface $request, string $message): Json|Redirect
    {
        if ($this->requestClassifier->isAsync($request)) {
            return $this->createJsonResult($message, $request);
        }

        return $this->createRedirectResult($message, $request);
    }

    public function createJsonResult(string $message, ?RequestInterface $request = null): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHttpResponseCode(401);
        $result->setData([
            'success' => false,
            'requiresLogin' => true,
            'loginUrl' => $this->buildLoginUrl($request),
            'registerUrl' => $this->buildRegisterUrl($request),
            'message' => $message,
        ]);

        return $result;
    }

    public function createRedirectResult(string $message, ?RequestInterface $request = null): Redirect
    {
        $this->messageManager->addErrorMessage(__($message));

        $result = $this->redirectFactory->create();
        $result->setUrl($this->buildLoginUrl($request));

        return $result;
    }

    public function buildLoginUrl(?RequestInterface $request = null): string
    {
        return $this->urlBuilder->getUrl('customer/account/login', [
            'referer' => $this->urlEncoder->encode($this->resolveTargetUrl($request)),
        ]);
    }

    public function buildRegisterUrl(?RequestInterface $request = null): string
    {
        return $this->urlBuilder->getUrl('customer/account/create', [
            'referer' => $this->urlEncoder->encode($this->resolveTargetUrl($request)),
        ]);
    }

    private function resolveTargetUrl(?RequestInterface $request): string
    {
        $fallback = $this->urlBuilder->getBaseUrl();

        if (!$request) {
            return $fallback;
        }

        $referer = method_exists($request, 'getHeader')
            ? trim((string)$request->getHeader('Referer'))
            : '';
        if (method_exists($request, 'isPost') && $request->isPost()) {
            return $referer !== '' ? $referer : $fallback;
        }

        $currentUrl = $this->urlBuilder->getCurrentUrl();

        return $currentUrl !== '' ? $currentUrl : ($referer !== '' ? $referer : $fallback);
    }
}
