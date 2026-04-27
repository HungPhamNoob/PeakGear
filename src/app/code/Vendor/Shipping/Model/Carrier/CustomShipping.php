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
        private \Vendor\Shipping\Service\GhtkApi $ghtkApi,
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

        // Đọc cấu hình từ Admin (Store > Configuration)
        $apiToken = (string)$this->getConfigData('api_token');
        $isSandbox = (bool)$this->getConfigFlag('sandbox_mode');
        $pickProvince = (string)$this->getConfigData('pick_province');
        $pickDistrict = (string)$this->getConfigData('pick_district');

        if (empty($apiToken) || empty($pickProvince) || empty($pickDistrict)) {
            $this->_logger->error('Vendor_Shipping: Missing GHTK API Token or Pickup Address in config.');
            return false;
        }

        // Lấy thông tin người nhận từ RateRequest
        // Lưu ý: Mặc định Magento destRegionID là ID của bảng directory_country_region.
        // Có thể ta sẽ cần mapping hoặc lấy thẳng Region (text)
        $destProvince = $request->getDestRegionCode() ?: $request->getDestRegionId(); 
        $destDistrict = $request->getDestCity();
        
        // Trọng lượng gói hàng (Magento mặc định có thể là Lbs, cần cấu hình Store là KG. GHTK yêu cầu Gram)
        $weightKg = (float)$request->getPackageWeight();
        if ($weightKg <= 0) {
            $weightKg = 1; // Mặc định 1kg nếu không set
        }
        $weightGram = (int)($weightKg * 1000); // Đổi sang Gram
        $value = (int)$request->getPackageValue();

        // Xây dựng Parameter gọi API
        $params = [
            'pick_province' => $pickProvince,
            'pick_district' => $pickDistrict,
            'province' => $destProvince ?: '',
            'district' => $destDistrict ?: '',
            'weight' => $weightGram,
            'value' => $value
        ];

        // Nếu người dùng chưa nhập tỉnh hoặc quận ở Checkout -> Không thể tính giá ship chính xác
        if (empty($params['province']) || empty($params['district'])) {
            return false;
        }

        // Gọi GHTK API
        $feeData = $this->ghtkApi->getFee($apiToken, $isSandbox, $params);

        if (!$feeData || !isset($feeData['fee'])) {
            return false; // Lỗi API hoặc không hỗ trợ giao hàng
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title') ?: 'Giao Hàng (GHTK)');

        $method->setMethod($this->_code);
        // Có thể lấy tên gói cước từ API (fee.name) nếu muốn
        $methodTitle = $this->getConfigData('name') ?: 'Giao Tiêu Chuẩn';
        if (isset($feeData['delivery_type'])) {
             $methodTitle .= ' (' . $feeData['delivery_type'] . ')';
        }
        $method->setMethodTitle($methodTitle);

        $shippingCost = (float)$feeData['fee'];

        $method->setPrice($shippingCost);
        $method->setCost($shippingCost);

        $result->append($method);

        return $result;
    }
}
