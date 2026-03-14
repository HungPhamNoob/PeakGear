<?php
namespace Me\RestApi\Model;

use Me\RestApi\Api\CustomerManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

class CustomerManagement implements CustomerManagementInterface
{
  protected $customerRepository;

  public function __construct(
    CustomerRepositoryInterface $customerRepository
  ) {
    $this->customerRepository = $customerRepository;
  }

  public function getCustomerById($customerId)
  {
    return $this->customerRepository->getById((int)$customerId);
  }

  public function searchCustomers(SearchCriteriaInterface $searchCriteria)
  {
    return $this->customerRepository->getList($searchCriteria);
  }
}