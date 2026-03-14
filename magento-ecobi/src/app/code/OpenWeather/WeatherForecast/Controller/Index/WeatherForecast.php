<?php
namespace OpenWeather\WeatherForecast\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;

class WeatherForecast extends Action
{
  protected $pageFactory;

  public function __construct(
    Context $context,
    PageFactory $pageFactory
  ) {
    parent::__construct($context);
    $this->pageFactory = $pageFactory;
  }

  public function execute()
  {
    return $this->pageFactory->create();
  }
}