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

        $returnCode = (int)($result['returncode'] ?? $result['return_code'] ?? 0);
        $orderUrl = (string)($result['orderurl'] ?? $result['order_url'] ?? '');
        $qrCode = (string)($result['zptranstoken'] ?? $result['zp_trans_token'] ?? $result['qr_code'] ?? '');

        if ($returnCode !== 1 || $orderUrl === '') {
            throw new LocalizedException(__(
                'ZaloPay: %1',
                $result['returnmessage'] ?? $result['return_message'] ?? 'Lỗi không xác định'
            ));
        }

        return [
            'order_url' => $orderUrl,
            'qr_code' => $qrCode,
            'app_trans_id' => $payload['app_trans_id'],
        ];
    }

    /**
     * Query transaction status by app_trans_id.
     *
     * @return array<string, mixed>
     */
    public function queryOrder(string $appTransId): array
    {
        if ($appTransId === '') {
            throw new LocalizedException(__('Missing app_trans_id for query.'));
        }

        if (!$this->config->isActive() || $this->config->getKey1() === '') {
            throw new LocalizedException(__('ZaloPay query is not fully configured.'));
        }

        $payload = [
            // v001/tpe/getstatusbyapptransid canonical field names
            'appid' => (int)$this->config->getAppId(),
            'apptransid' => $appTransId,
            // Compatibility aliases for underscore-style integrations
            'app_id' => (int)$this->config->getAppId(),
            'app_trans_id' => $appTransId,
            'mac' => $this->signatureService->buildQueryMac($appTransId),
        ];

        return $this->apiClient->queryOrder($payload);
    }

    /**
     * A successful query should have return_code = 1.
     */
    public function isQueryPaid(array $result): bool
    {
        return (int)($result['returncode'] ?? $result['return_code'] ?? 0) === 1;
    }

    /**
     * Verify ZaloPay callback with HMAC-SHA256.
     */
    public function verifyCallback(array $data): bool
    {
        return $this->signatureService->verifyCallback($data);
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        // Keep method visible in checkout even when gateway keys are missing.
        // Frontend action button handles disabled state via checkoutConfig.
        return parent::isAvailable($quote);
    }
}
