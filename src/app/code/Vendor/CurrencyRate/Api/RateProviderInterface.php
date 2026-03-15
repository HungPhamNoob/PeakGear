<?php
declare(strict_types=1);

namespace Vendor\CurrencyRate\Api;

/**
 * Fetches remote currency rates for the storefront widget and cron refresh flow.
 */
interface RateProviderInterface
{
    /**
     * Return normalized rates indexed by ISO currency code.
     *
     * @return array<string, array{name:string, buy_transfer:float, buy_cash:float, sell:float}>
     */
    public function fetchRates(): array;
}
