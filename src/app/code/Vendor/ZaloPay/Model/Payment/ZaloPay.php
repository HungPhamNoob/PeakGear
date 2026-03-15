<?php
declare(strict_types=1);

namespace Vendor\ZaloPay\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Exception\LocalizedException;
use Vendor\ZaloPay\Model\Config;

/**
 * Thin payment method facade delegating request/verification work to focused services.
 */
class ZaloPay extends AbstractMethod
{
    public const CODE = 'zalopay';

    protected $_code                   = self::CODE;
    protected $_isGateway              = true;
    protected $_canCapture             = true;
    protected $_canAuthorize           = true;
    protected $_canRefund              = false;
    protected $_canUseForMultishipping = false;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        private readonly Config $config,
        private readonly CreateOrderRequestBuilder $requestBuilder,
        private readonly ApiClient $apiClient,
        private readonly SignatureService $signatureService,
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
    }

    public function createOrder(
        string $orderId,
        int    $amount,
        string $description,
        string $callbackUrl,
        string $redirectUrl
    ): array {
        if (!$this->config->isActive() || $this->config->getKey1() === '' || $this->config->getKey2() === '') {
            throw new LocalizedException(__('ZaloPay is not fully configured.'));
        }

        $payload = $this->requestBuilder->build($orderId, $amount, $description, $callbackUrl, $redirectUrl);
        $result = $this->apiClient->createOrder($payload['post_data']);

        if (($result['return_code'] ?? 0) !== 1) {
            throw new LocalizedException(__(
                'ZaloPay: %1',
                $result['return_message'] ?? 'Lỗi không xác định'
            ));
        }

        return [
            'order_url' => (string)$result['order_url'],
            'app_trans_id' => $payload['app_trans_id'],
        ];
    }

    /**
     * Verify ZaloPay callback with HMAC-SHA256.
     */
    public function verifyCallback(array $data): bool
    {
        return $this->signatureService->verifyCallback($data);
    }
}
