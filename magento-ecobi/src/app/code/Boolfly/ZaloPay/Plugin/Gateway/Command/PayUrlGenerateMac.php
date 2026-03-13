<?php
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   Layered Navigation
 */
namespace Boolfly\ZaloPay\Plugin\Gateway\Command;

use Boolfly\ZaloPay\Gateway\Helper\Authorization;
use Boolfly\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Magento\Payment\Gateway\Request\BuilderComposite;

/**
 * Class PayUrlGenerateMac
 *
 * @package Boolfly\ZaloPay\Plugin\Gateway\Request
 * @see BuilderComposite
 */
class PayUrlGenerateMac
{
  /**
   * @var Authorization
   */
  private $authorization;

  /**
   * PayUrlGenerateMac constructor.
   *
   * @param Authorization $authorization
   */
  public function __construct(
    Authorization $authorization
  ) {
    $this->authorization = $authorization;
  }

  /**
   * Generate Mac
   *
   * @param BuilderComposite $subject
   * @param $result
   */
  public function afterBuildRequestData($subject, $result)
  {
    $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/zalopay_debug.log');
    $logger = new \Zend_Log();
    $logger->addWriter($writer);

    $logger->info(print_r($result, true));
    if (is_array($result)) {
      $newParams = [];
      foreach ($this->getMacKeys() as $key) {
        if (!empty($result[$key])) {
          $newParams[] = $result[$key];
        }
      }
      $result[AbstractDataBuilder::MAC] = $this->authorization->getMac($newParams);
    }

    return $result;
  }

  /**
   * @return array
   */
  protected function getMacKeys()
  {
    return [
      AbstractDataBuilder::APP_ID,
      AbstractDataBuilder::APP_TRANS_ID,
      AbstractDataBuilder::APP_USER,
      AbstractDataBuilder::AMOUNT,
      AbstractDataBuilder::APP_TIME,
      AbstractDataBuilder::EMBED_DATA,
      AbstractDataBuilder::ITEM
    ];
  }
}
