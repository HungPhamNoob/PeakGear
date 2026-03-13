<?php
namespace News\VNExpress\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\HTTP\Client\Curl;

class News extends Template
{
  protected $curl;

  public function __construct(
    Template\Context $context,
    Curl $curl,
    array $data = []
  ) {
    $this->curl = $curl;
    parent::__construct($context, $data);
  }

  public function getRssFeed()
  {
    $url = 'https://vnexpress.net/rss/kinh-doanh.rss';
    $this->curl->get($url);

    $response = $this->curl->getBody();
    $xml = simplexml_load_string($response);

    return $xml;
  }
}
