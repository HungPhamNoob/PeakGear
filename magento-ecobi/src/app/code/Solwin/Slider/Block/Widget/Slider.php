<?php
namespace Solwin\Slider\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Magento\Store\Model\StoreManagerInterface;

class Slider extends Template implements BlockInterface
{
  protected $_template = "Solwin_Slider::slider.phtml";

  protected $storeManager;

  public function __construct(
    Template\Context $context,
    StoreManagerInterface $storeManager,
    array $data = []
  ) {
    $this->storeManager = $storeManager;
    parent::__construct($context, $data);
  }

  public function getSlides()
  {
    $slides = $this->getData('slides');

    if (empty($slides)) {
      return [
        [
          'title' => 'UNLEASH YOUR POWER',
          'subtitle' => 'E-COMMERCE BILLIARD SHOP',
          'button_text' => 'SHOP NOW',
          'button_url' => '/cues.html',
          'image' => $this->getViewFileUrl('Solwin_Slider::images/slide-1.png')
        ],
        [
          'title' => 'MAXIMIZE PERFORMANCE',
          'subtitle' => 'E-COMMERCE BILLIARD SHOP',
          'button_text' => 'DISCOVER',
          'button_url' => '#',
          'image' => $this->getViewFileUrl('Solwin_Slider::images/slide-2.png')
        ],
        [
          'title' => 'MEET THE WORLD!',
          'subtitle' => 'E-COMMERCE BILLIARD SHOP',
          'button_text' => 'DISCOVER',
          'button_url' => '/cues.html',
          'image' => $this->getViewFileUrl('Solwin_Slider::images/slide-3.png')
        ],
        [
          'title' => 'LET\' THE MATCH BEGIN!',
          'subtitle' => 'E-COMMERCE BILLIARD SHOP',
          'button_text' => 'DISCOVER',
          'button_url' => '/cues.html',
          'image' => $this->getViewFileUrl('Solwin_Slider::images/slide-4.png')
        ],
        [
          'title' => 'UNLEASH YOUR POWER',
          'subtitle' => 'E-COMMERCE BILLIARD SHOP',
          'button_text' => 'DISCOVER',
          'button_url' => '/cues.html',
          'image' => $this->getViewFileUrl('Solwin_Slider::images/slide-5.png')
        ],
        [
          'title' => 'MAXIMIZE PERFORMANCE',
          'subtitle' => 'E-COMMERCE BILLIARD SHOP',
          'button_text' => 'DISCOVER',
          'button_url' => '/cues.html',
          'image' => $this->getViewFileUrl('Solwin_Slider::images/slide-6.png')
        ]
      ];
    }

    if (!is_array($slides)) {
      $decoded = json_decode($slides, true);
      return $decoded ?: [];
    }

    return $slides;
  }

  public function getAutoPlay()
  {
    return $this->getData('auto_play');
  }

  public function getSlideSpeed()
  {
    return $this->getData('slide_speed') ?: 5000;
  }
}