<?php
namespace Solwin\Slider\Block\Adminhtml\Widget;

class Slides extends \Magento\Backend\Block\Template
{
  protected $_template = 'Solwin_Slider::widget/slides.phtml';

  public function prepareElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
  {
    $html = $this->toHtml();
    $element->setData('after_element_html', $html);
    return $element;
  }
}