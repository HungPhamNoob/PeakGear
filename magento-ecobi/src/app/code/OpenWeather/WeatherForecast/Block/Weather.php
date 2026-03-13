<?php
namespace OpenWeather\WeatherForecast\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use OpenWeather\WeatherForecast\Helper\Data as WeatherHelper;
use Magento\Framework\Message\Collection;
use Magento\Framework\Message\Factory;

class Weather extends Template
{
  protected $weatherHelper;
  protected $messageFactory;
  protected $messages;

  public function __construct(
    Context $context,
    WeatherHelper $weatherHelper,
    Factory $messageFactory,
    array $data = []
  ) {
    $this->weatherHelper = $weatherHelper;
    $this->messageFactory = $messageFactory;
    $this->messages = new Collection();
    parent::__construct($context, $data);
  }

  public function getWeatherData($city = null)
  {
    $result = $this->weatherHelper->getWeatherData($city);

    if ($result['success']) {
      return $result['data'];
    }

    $errorMessage = $this->messageFactory->create(
      \Magento\Framework\Message\MessageInterface::TYPE_ERROR,
      $result['message']
    );
    $this->messages->addMessage($errorMessage);

    return [];
  }

  public function getErrorMessages()
  {
    return $this->messages;
  }

  public function hasErrors()
  {
    return $this->messages->getCount() > 0;
  }
}