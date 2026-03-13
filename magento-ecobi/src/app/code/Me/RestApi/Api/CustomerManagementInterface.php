<?php
namespace Me\RestApi\Api;

interface CustomerManagementInterface
{
  /**
   * Get customer by ID
   *
   * @param string $customerId
   * @return \Magento\Customer\Api\Data\CustomerInterface
   * @throws \Magento\Framework\Exception\NoSuchEntityException
   */
  public function getCustomerById($customerId);

  /**
   * Search customers
   *
   * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
   * @return \Magento\Customer\Api\Data\CustomerSearchResultsInterface
   */
  public function searchCustomers(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
}