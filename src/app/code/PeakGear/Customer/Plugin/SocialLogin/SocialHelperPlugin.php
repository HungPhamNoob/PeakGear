<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin\SocialLogin;

use Mageplaza\SocialLogin\Helper\Social;

class SocialHelperPlugin
{
    public function afterGetSocialConfig(Social $subject, array $result, string $type): array
    {
        if ($result) {
            return $result;
        }

        return match (strtolower($type)) {
            'google' => ['scope' => 'email profile'],
            'twitter' => ['includeEmail' => true],
            'linkedin' => ['fields' => ['id', 'first-name', 'last-name', 'email-address']],
            'yahoo' => ['scope' => 'profile'],
            default => $result,
        };
    }
}
