<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Customer\Controller\Ajax\Login;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PeakGear\Customer\Model\CustomerPhoneResolver;
use PeakGear\Customer\Model\PhoneNormalizer;

class AjaxLoginPlugin
{
    public function __construct(
        private readonly CustomerPhoneResolver $customerPhoneResolver,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly Json $json
    ) {
    }

    public function beforeExecute(Login $subject): void
    {
        $request = $subject->getRequest();
        $content = (string) $request->getContent();

        if ($content === '') {
            return;
        }

        try {
            $credentials = $this->json->unserialize($content);
        } catch (\InvalidArgumentException) {
            return;
        }

        if (!is_array($credentials)) {
            return;
        }

        $username = trim((string) ($credentials['username'] ?? ''));
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

        $credentials['username'] = $email;

        if (method_exists($request, 'setContent')) {
            $request->setContent($this->json->serialize($credentials));
        }
    }
}
