<?php
namespace Me\RestApi\Api;

interface ProductManagementInterface
{
  /**
   * Get product by SKU
   *
   * @param string $sku
   * @return \Magento\Catalog\Api\Data\ProductInterface
   * @throws \Magento\Framework\Exception\NoSuchEntityException
   */
  public function getProductBySku($sku);

  /**
   * Search products
   *
   * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
   * @return \Magento\Catalog\Api\Data\ProductSearchResultsInterface
   */
  public function searchProducts(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
}