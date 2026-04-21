<?php
declare(strict_types=1);

namespace Vendor\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Psr\Log\LoggerInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;

class CustomShipping extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'vendor_shipping';

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private ResultFactory $rateResultFactory,
        private MethodFactory $rateMethodFactory,
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
            return false; // Module bị tắt trong admin
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title') ?: 'Giao Hàng (Vendor API)');

        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name') ?: 'Giao Tiêu Chuẩn');

        /* 
         * TODO: LẤY THÔNG TIN ĐỂ GỬI API 
         * $destCity = $request->getDestCity();
         * $destRegion = $request->getDestRegionId(); 
         * $weight = $request->getPackageWeight();
         */
         
        // TẠM THỜI: Mock giá vận chuyển là 35.000 VNĐ để test Checkout flow
        $shippingCost = 35000;

        $method->setPrice($shippingCost);
        $method->setCost($shippingCost);

        $result->append($method);

        return $result;
    }
}
