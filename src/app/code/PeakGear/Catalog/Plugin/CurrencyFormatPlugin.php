<?php
declare(strict_types=1);

namespace PeakGear\Catalog\Plugin;

use Magento\Directory\Model\Currency;

class CurrencyFormatPlugin
{
    /**
     * Intercept and fix currency format for VND globally (PHP render)
     *
     * @param Currency $subject
     * @param string $result
     * @param float $price
     * @param array $options
     * @return string
     */
    public function afterFormatTxt(Currency $subject, string $result, $price, $options = []): string
    {
        if (str_contains($result, '₫') || str_contains($result, 'VND')) {
            // Remove trailing .00 for VND
            $result = preg_replace('/\.00(?=[^\d]|$)/', '', $result);
            
            // Move symbol to the end if it's at the beginning (e.g. ₫1,199,000 -> 1,199,000₫)
            $result = preg_replace('/(₫|VND)\s*([\d,\.]+)/', '$2$1', $result);
        }

        return $result;
    }
}
