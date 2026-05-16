<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin\SocialLogin;

use Mageplaza\SocialLogin\Helper\Data;

class DataPlugin
{
    public function afterIsCheckMode(Data $subject, bool $result): bool
    {
        return false;
    }
}
