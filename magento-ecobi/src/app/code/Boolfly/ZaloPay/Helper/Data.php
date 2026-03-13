<?php
namespace Boolfly\ZaloPay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Data extends AbstractHelper
{
  /**
   * Log data in debug.log
   *
   * @param mixed $data
   * @param string $title
   * @return void
   */
  public function debug($data, $title = 'ZaloPay Debug')
  {
    $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/zalopay_debug.log');
    $logger = new \Zend_Log();
    $logger->addWriter($writer);

    // Add delimiter for better readability
    $logger->info("\n=== {$title} " . date('Y-m-d H:i:s') . " ===");

    if (is_array($data) || is_object($data)) {
      $logger->info(print_r($data, true));
    } else {
      $logger->info($data);
    }

    $logger->info("=== End {$title} ===\n");
  }
}