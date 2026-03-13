<?php
namespace Me\DecimalDelimiter\Model\Plugin;

use Magento\Framework\Pricing\PriceCurrencyInterface;

class PriceCurrency extends PriceFormatPluginAbstract
{
  public function aroundFormat(
    PriceCurrencyInterface $subject,
    \Closure $proceed,
    $amount,
    $includeContainer = true,
    $precision = PriceCurrencyInterface::DEFAULT_PRECISION,
    $scope = null,
    $currency = null
  ) {
    $result = $proceed($amount, $includeContainer, $precision, $scope, $currency);
    return $this->formatPrice($result);
  }
}
