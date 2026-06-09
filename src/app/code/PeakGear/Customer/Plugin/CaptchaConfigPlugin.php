<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Captcha\Helper\Data;

class CaptchaConfigPlugin
{
    public function afterGetConfig(Data $subject, mixed $result, string $key, $store = null): mixed
    {
        if (strtolower($key) !== 'forms' || !is_string($result) || $result === '') {
            return $result;
        }

        $forms = array_values(array_filter(array_map('trim', explode(',', $result))));
        $forms = array_values(array_filter($forms, static fn (string $formId): bool => $formId !== 'user_login'));

        return implode(',', $forms);
    }
}
