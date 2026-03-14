<?php
namespace Me\RestApi\Model;

use Me\RestApi\Api\ProductManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

class ProductManagement implements ProductManagementInterface
{
  protected $productRepository;

  public function __construct(
    ProductRepositoryInterface $productRepository
  ) {
    $this->productRepository = $productRepository;
  }

  public function getProductBySku($sku)
  {
    return $this->productRepository->get($sku);
  }

  public function searchProducts(SearchCriteriaInterface $searchCriteria)
  {
    return $this->productRepository->getList($searchCriteria);
  }
}