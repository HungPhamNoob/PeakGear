<?php
namespace Me\DecimalDelimiter\Model\Plugin;

use Me\DecimalDelimiter\Helper\Data as DecimalHelper;

abstract class PriceFormatPluginAbstract
{
  protected $decimalHelper;

  public function __construct(
    DecimalHelper $decimalHelper
  ) {
    $this->decimalHelper = $decimalHelper;
  }

  protected function formatPrice($price)
  {
    if (is_string($price)) {
      $delimiter = $this->decimalHelper->getDecimalDelimiter();
      if ($delimiter !== '.') {
        return str_replace('.', $delimiter, $price);
      }
    }
    return $price;
  }
}
