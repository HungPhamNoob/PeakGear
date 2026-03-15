<?php
declare(strict_types=1);

namespace Vendor\VNPay\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Exception\LocalizedException;
use Vendor\VNPay\Model\Config;

/**
 * Thin payment facade delegating redirect payload and signature work.
 */
class VNPay extends AbstractMethod
{
    public const CODE = 'vnpay';

    protected $_code                 = self::CODE;
    protected $_isGateway            = true;
    protected $_canCapture           = true;
    protected $_canAuthorize         = true;
    protected $_canRefund            = false;
    protected $_canUseForMultishipping = false;

    private Config $config;
    private RedirectRequestBuilder $redirectRequestBuilder;
    private SignatureService $signatureService;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        ?Config $config = null,
        ?RedirectRequestBuilder $redirectRequestBuilder = null,
        ?SignatureService $signatureService = null,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        // Keep constructor backward-compatible with stale compiled metadata that may pass nulls.
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->config = $config ?: $objectManager->get(Config::class);
        $this->redirectRequestBuilder = $redirectRequestBuilder ?: $objectManager->get(RedirectRequestBuilder::class);
        $this->signatureService = $signatureService ?: $objectManager->get(SignatureService::class);
    }

    /**
     * Build VNPay redirect URL with HMAC-SHA512 signature.
     */
    public function buildRedirectUrl(
        string $orderId,
        int    $amount,
        string $orderInfo,
        string $returnUrl,
        string $ipAddr,
        string $locale = 'vn'
    ): string {
        if (!$this->config->isActive() || $this->config->getTmnCode() === '' || $this->config->getHashSecret() === '') {
            throw new LocalizedException(__('VNPay is not fully configured.'));
        }

        return $this->redirectRequestBuilder->buildUrl(
            $orderId,
            $amount,
            $orderInfo,
            $returnUrl,
            $ipAddr,
            $locale
        );
    }

    /**
     * Verify VNPay callback with HMAC-SHA512.
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function verifyCallback(array $params): bool
    {
        if (!$this->signatureService->verify($params)) {
            throw new LocalizedException(__('VNPay: Chữ ký không hợp lệ'));
        }

        return $params['vnp_ResponseCode'] === '00';
    }

    public function getReturnUrlPath(): string
    {
        return $this->config->getReturnUrlPath();
    }
}
