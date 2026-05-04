<?php
declare(strict_types=1);

namespace Vendor\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;
use Vendor\Shipping\Model\Config;
use Vendor\Shipping\Service\RequestPayloadBuilder;
use Vendor\Shipping\Service\WeightResolver;

class CustomShipping extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'vendor_shipping';

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly \Vendor\Shipping\Service\GhtkApi $ghtkApi,
        private readonly Config $config,
        private readonly RequestPayloadBuilder $requestPayloadBuilder,
        private readonly WeightResolver $weightResolver,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function getAllowedMethods(): array
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    public function collectRates(RateRequest $request): bool|Result
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $storeId = $request->getStoreId() !== null ? (int)$request->getStoreId() : null;
        $apiToken = trim($this->config->getApiToken($storeId));
        $clientSource = trim($this->config->getClientSource($storeId));
        $pickProvince = trim($this->config->getPickProvince($storeId));
        $pickDistrict = trim($this->config->getPickDistrict($storeId));

        if ($apiToken === '' || $clientSource === '' || $pickProvince === '' || $pickDistrict === '') {
            return $this->buildFallbackResult($storeId, 'Missing GHTK credentials or pickup address.');
        }

        $weightGram = $this->weightResolver->resolveGram($request, $storeId);
        $params = $this->requestPayloadBuilder->build($request, $weightGram, $storeId);

        if (empty($params['province']) || empty($params['district'])) {
            return $this->buildFallbackResult($storeId, 'Missing destination province or district.');
        }

        $feeData = $this->ghtkApi->getFee(
            $apiToken,
            $clientSource,
            $this->config->isSandboxMode($storeId),
            $params,
            $this->config->getTimeout($storeId),
            $this->config->getCacheTtl($storeId),
            $this->config->isDebug($storeId)
        );

        if (!$feeData || !isset($feeData['fee']) || (($feeData['delivery'] ?? true) === false)) {
            return $this->buildFallbackResult($storeId, 'GHTK returned no valid delivery fee.');
        }

        $methodTitle = $this->config->getMethodName($storeId);
        if (!empty($feeData['name'])) {
            $methodTitle .= ' - ' . (string)$feeData['name'];
        }

        return $this->buildResult((float)$feeData['fee'], $storeId, $methodTitle);
    }

    private function buildFallbackResult(?int $storeId, string $reason): bool|Result
    {
        if ($this->config->isDebug($storeId)) {
            $this->_logger->warning('Vendor_Shipping fallback used.', ['reason' => $reason]);
        }

        if (!$this->config->isFallbackEnabled($storeId)) {
            return false;
        }

        return $this->buildResult(
            $this->config->getFallbackPrice($storeId),
            $storeId,
            $this->config->getMethodName($storeId) . ' (fallback)'
        );
    }

    private function buildResult(float $shippingCost, ?int $storeId, string $methodTitle): Result
    {
        $result = $this->rateResultFactory->create();
        $method = $this->rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->config->getTitle($storeId));
        $method->setMethod($this->_code);
        $method->setMethodTitle($methodTitle);
        $method->setPrice($shippingCost);
        $method->setCost($shippingCost);
        $result->append($method);

        return $result;
    }
}
