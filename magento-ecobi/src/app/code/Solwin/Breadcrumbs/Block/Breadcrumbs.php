<?php
namespace Solwin\Breadcrumbs\Block;

use Magento\Framework\View\Element\Template\Context;
use Magento\Catalog\Helper\Data;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Framework\App\Request\Http;
use Magento\Catalog\Model\CategoryFactory;

class Breadcrumbs extends \Magento\Framework\View\Element\Template
{
  protected $_catalogData = null;
  protected $registry;
  protected $request;
  protected $categoryFactory;

  public function __construct(
    Context $context,
    Data $catalogData,
    Registry $registry,
    Http $request,
    CategoryFactory $categoryFactory,
    array $data = []
  ) {
    $this->_catalogData = $catalogData;
    $this->registry = $registry;
    $this->request = $request;
    $this->categoryFactory = $categoryFactory;
    parent::__construct($context, $data);
  }

  public function getCrumbs()
  {
    $crumbs = [];

    // Add 'Home' link
    $crumbs[] = [
      'label' => __('Home'),
      'title' => __('Go to Home Page'),
      'link' => $this->_storeManager->getStore()->getBaseUrl()
    ];

    // Get current product
    $product = $this->registry->registry('current_product');
    // Get current category
    $category = $this->registry->registry('current_category');

    if ($product) {
      // Add category path
      $categories = $product->getCategoryCollection()
        ->addAttributeToSelect('name')
        ->addAttributeToSelect('url_key')
        ->addAttributeToSelect('is_active')
        ->addAttributeToFilter('is_active', 1)
        ->setOrder('level', 'ASC');

      foreach ($categories as $cat) {
        $pathIds = $cat->getPathIds();
        // Remove first two levels: root and default category
        array_shift($pathIds);
        array_shift($pathIds);

        foreach ($pathIds as $categoryId) {
          $_category = $this->categoryFactory->create()->load($categoryId);
          if ($_category->getIsActive()) {
            $crumbs[] = [
              'label' => $_category->getName(),
              'title' => $_category->getName(),
              'link' => $_category->getUrl()
            ];
          }
        }
      }

      // Add product name
      $crumbs[] = [
        'label' => $product->getName(),
        'title' => $product->getName(),
        'link' => ''
      ];
    } elseif ($category) {
      $pathIds = $category->getPathIds();
      // Remove first two levels: root and default category
      array_shift($pathIds);
      array_shift($pathIds);

      foreach ($pathIds as $categoryId) {
        $_category = $this->categoryFactory->create()->load($categoryId);
        if ($_category->getIsActive()) {
          $crumbs[] = [
            'label' => $_category->getName(),
            'title' => $_category->getName(),
            'link' => $_category->getUrl()
          ];
        }
      }
    }

    return $crumbs;
  }
}