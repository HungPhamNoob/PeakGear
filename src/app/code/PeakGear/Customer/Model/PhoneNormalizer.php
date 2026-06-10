<?php
declare(strict_types=1);

namespace PeakGear\Customer\Model;

class PhoneNormalizer
{
    public function normalize(string $phone): string
    {
        $phone = trim($phone);
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if ($hasPlus && str_starts_with($digits, '84')) {
            return '0' . substr($digits, 2);
        }

        if (str_starts_with($digits, '84') && strlen($digits) >= 11) {
            return '0' . substr($digits, 2);
        }

        return $digits;
    }

    public function isValid(string $phone): bool
    {
        return (bool) preg_match('/^0[1-9][0-9]{8,9}$/', $this->normalize($phone));
    }

    public function isEmail(string $value): bool
    {
        return (bool) filter_var(trim($value), FILTER_VALIDATE_EMAIL);
    }

    public function createInternalEmail(string $phone): string
    {
        return 'phone-' . $this->normalize($phone) . '@peakgear.local';
    }
}
