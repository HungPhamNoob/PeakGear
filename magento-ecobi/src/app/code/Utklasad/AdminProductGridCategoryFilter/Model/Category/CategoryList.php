<?php

namespace Utklasad\AdminProductGridCategoryFilter\Model\Category;

use Magento\Framework\Option\ArrayInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

class CategoryList implements ArrayInterface
{
  protected $_categoryCollectionFactory;

  public function __construct(
    CollectionFactory $collectionFactory
  ) {
    $this->_categoryCollectionFactory = $collectionFactory;
  }

  public function toOptionArray($addEmpty = true)
  {
    $categoryCollection = $this->_categoryCollectionFactory->create()->addAttributeToSelect('name')->setOrder('name', 'ASC');

    $options = [];

    if ($addEmpty) {
      $options[] = ['label' => __('-- Please Select a Category --'), 'value' => ''];
    }

    foreach ($categoryCollection as $category) {
      $pathIds = $category->getPathIds();
      // Remove the first two IDs (root and default category)
      array_shift($pathIds);
      array_shift($pathIds);

      $pathNames = [];
      foreach ($pathIds as $categoryId) {
        $pathCategory = $categoryCollection->getItemById($categoryId);
        if ($pathCategory) {
          $pathNames[] = $pathCategory->getName();
        }
      }

      $label = implode('/', $pathNames);
      if (!empty($label)) {
        $options[] = [
          'label' => $label,
          'value' => $category->getId()
        ];
      }
    }

    return $options;
  }
}