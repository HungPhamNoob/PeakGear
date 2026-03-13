<?php
namespace Me\DecimalDelimiter\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
  const XML_PATH_DECIMAL_DELIMITER = 'catalog/price/decimal_delimiter';

  public function getDecimalDelimiter($store = null)
  {
    return $this->scopeConfig->getValue(
      self::XML_PATH_DECIMAL_DELIMITER,
      ScopeInterface::SCOPE_STORE,
      $store
    );
  }
}
