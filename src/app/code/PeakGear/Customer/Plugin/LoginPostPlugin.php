<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Customer\Controller\Account\LoginPost;
use Magento\Framework\Exception\LocalizedException;
use PeakGear\Customer\Model\CustomerPhoneResolver;
use PeakGear\Customer\Model\PhoneNormalizer;

class LoginPostPlugin
{
    public function __construct(
        private readonly CustomerPhoneResolver $customerPhoneResolver,
        private readonly PhoneNormalizer $phoneNormalizer
    ) {
    }

    public function beforeExecute(LoginPost $subject): void
    {
        $request = $subject->getRequest();
        $login = (array) $request->getParam('login', []);
        $username = trim((string) ($login['username'] ?? ''));

        if ($username === '' || $this->phoneNormalizer->isEmail($username)) {
            return;
        }

        try {
            $email = $this->customerPhoneResolver->findEmailByPhone($username);
        } catch (LocalizedException) {
            return;
        }

        if (!$email) {
            return;
        }

        $login['username'] = $email;
        $request->setParam('login', $login);
        if (method_exists($request, 'setPostValue')) {
            $request->setPostValue('login', $login);
        }
    }
}
