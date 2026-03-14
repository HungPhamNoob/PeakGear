<?php
namespace Boolfly\ZaloPay\Gateway\Command;

use Boolfly\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Boolfly\ZaloPay\Helper\Data as ZaloPayHelper;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;

class GetPayUrlCommand implements CommandInterface
{
  /**
   * @var BuilderInterface
   */
  private $requestBuilder;

  /**
   * @var TransferFactoryInterface
   */
  private $transferFactory;

  /**
   * @var ClientInterface
   */
  private $client;

  /**
   * @var ValidatorInterface
   */
  private $validator;

  /**
   * @var ArrayResultFactory
   */
  private $resultFactory;

  /**
   * @var ZaloPayHelper
   */
  private $zaloPayHelper;

  public function __construct(
    BuilderInterface $requestBuilder,
    TransferFactoryInterface $transferFactory,
    ClientInterface $client,
    ArrayResultFactory $resultFactory,
    ValidatorInterface $validator,
    ZaloPayHelper $zaloPayHelper
  ) {
    $this->requestBuilder = $requestBuilder;
    $this->transferFactory = $transferFactory;
    $this->client = $client;
    $this->resultFactory = $resultFactory;
    $this->validator = $validator;
    $this->zaloPayHelper = $zaloPayHelper;
  }

  public function execute(array $commandSubject)
  {
    try {
      // Build request data
      $requestData = $this->buildRequestData($commandSubject);
      $this->zaloPayHelper->debug($requestData, 'ZaloPay Request Data');

      // Create transfer object
      $transferO = $this->transferFactory->create($requestData);

      // Place request
      $response = $this->client->placeRequest($transferO);
      $this->zaloPayHelper->debug($response, 'ZaloPay Response');

      return $this->resultFactory->create(
        [
          'array' => [
            AbstractResponseValidator::PAY_URL => $response[AbstractResponseValidator::PAY_URL]
          ]
        ]
      );
    } catch (\Exception $e) {
      $this->zaloPayHelper->debug([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ], 'ZaloPay Error');
      throw $e;
    }
  }

  public function buildRequestData(array $commandSubject)
  {
    return $this->requestBuilder->build($commandSubject);
  }
}