<?php
namespace OpenWeather\WeatherForecast\Observer;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Data\Tree\Node;
use Magento\Framework\Event\ObserverInterface;
class Menu implements ObserverInterface
{
  public function execute(EventObserver $observer)
  {
    $menu = $observer->getMenu();
    $tree = $menu->getTree();
    $data = [
      'name' => __('WEATHER'),
      'id' => 'weather',
      'url' => '/weather/index/weatherforecast',
      'is_active' => false
    ];
    $node = new Node($data, 'id', $tree, $menu);
    $menu->addChild($node);
    return $this;
  }
}
?>