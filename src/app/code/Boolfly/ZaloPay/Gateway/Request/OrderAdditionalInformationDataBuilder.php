<?php
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   ZaloPay
 */
namespace Boolfly\ZaloPay\Gateway\Request;

use Boolfly\ZaloPay\Gateway\Helper\Rate;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\UrlInterface;


/**
 * Class OrderAdditionalInformationDataBuilder
 *
 * @package Boolfly\ZaloPay\Gateway\Request
 */
class OrderAdditionalInformationDataBuilder extends AbstractDataBuilder implements BuilderInterface
{
  /**
   * Zalo Pay App
   */
  const ZALOPAY_APP = 'zalopayapp';

  /**
   * @var Json
   */
  private $serializer;

  /**
   * @var Rate
   */
  private $helperRate;

  private $urlBuilder;

  /**
   * OrderAdditionalInformationDataBuilder constructor.
   *
   * @param Json $serializer
   * @param Rate $helperRate
   */
  public function __construct(
    Json $serializer,
    Rate $helperRate,
    UrlInterface $urlBuilder
  ) {
    $this->serializer = $serializer;
    $this->helperRate = $helperRate;
    $this->urlBuilder = $urlBuilder;
  }

  /**
   * @param array $buildSubject
   * @return array
   * @throws LocalizedException
   * @throws NoSuchEntityException
   */
  public function build(array $buildSubject)
  {
    $payment = SubjectReader::readPayment($buildSubject);
    $order = $payment->getPayment()->getOrder();

    return [
      self::EMBED_DATA => $this->getEmbedData(),
      self::AMOUNT => (int) $this->helperRate->getVndAmount($order, round((float) SubjectReader::readAmount($buildSubject), 2)),
      self::DESCRIPTION => "Ecobi_Payment_For_Order_" . $order->getId(),
      self::BANK_CODE => self::ZALOPAY_APP,
      'callback_url' => $this->urlBuilder->getUrl('zalopay/payment/callback'),
    ];
  }

  /**
   * @return string
   */
  private function getEmbedData()
  {
    return '{"redirecturl":"' . $this->urlBuilder->getBaseUrl() . '"}';
  }
}
