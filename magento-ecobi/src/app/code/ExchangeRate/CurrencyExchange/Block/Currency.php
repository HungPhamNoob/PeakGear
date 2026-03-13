<?php
namespace ExchangeRate\CurrencyExchange\Block;

use Magento\Framework\View\Element\Template;
use ExchangeRate\CurrencyExchange\Helper\Data;

class Currency extends Template
{
    protected $dataHelper;

    public function __construct(
        Template\Context $context,
        Data $dataHelper,
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;
        parent::__construct($context, $data);
    }

    public function getRates()
    {
        return $this->dataHelper->getCurrencyRates();
    }
}
