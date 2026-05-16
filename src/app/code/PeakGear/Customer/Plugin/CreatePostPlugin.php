<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Validator\EmailAddress as EmailValidator;
use PeakGear\Customer\Model\CustomerPhoneResolver;
use PeakGear\Customer\Model\PhoneNormalizer;

class CreatePostPlugin
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param ManagerInterface $messageManager
     * @param RequestInterface $request
     */
    public function __construct(
        ManagerInterface $messageManager,
        RequestInterface $request,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly CustomerPhoneResolver $customerPhoneResolver,
        private readonly EmailValidator $emailValidator
    ) {
        $this->messageManager = $messageManager;
        $this->request = $request;
    }

    /**
     * Validate terms agreement before creating account
     *
     * @param \Magento\Customer\Controller\Account\CreatePost $subject
     * @return void
     * @throws InputException
     */
    public function beforeExecute(
        \Magento\Customer\Controller\Account\CreatePost $subject
    ): void {
        $termsAgree = $this->request->getParam('terms_agree');
        if (!$termsAgree) {
            throw new InputException(
                __('Bạn cần đồng ý với điều khoản sử dụng.')
            );
        }

        $createMethod = (string) $this->request->getParam('account_create_method', 'email');
        $telephone = (string) $this->request->getParam('telephone', '');
        $email = trim((string) $this->request->getParam('email', ''));

        if ($createMethod === 'phone') {
            $this->assertPhoneAttributeReady();

            if (!$this->phoneNormalizer->isValid($telephone)) {
                throw new InputException(__('Vui lòng nhập số điện thoại hợp lệ.'));
            }

            $normalizedTelephone = $this->phoneNormalizer->normalize($telephone);
            if ($this->customerPhoneResolver->phoneExists($normalizedTelephone)) {
                throw new InputException(__('Số điện thoại này đã được sử dụng cho tài khoản khác.'));
            }

            $this->request->setParam('telephone', $normalizedTelephone);
            $this->request->setParam('email', $this->phoneNormalizer->createInternalEmail($normalizedTelephone));
            if (method_exists($this->request, 'setPostValue')) {
                $this->request->setPostValue('telephone', $normalizedTelephone);
                $this->request->setPostValue('email', $this->phoneNormalizer->createInternalEmail($normalizedTelephone));
            }

            return;
        }

        if (!$this->emailValidator->isValid($email)) {
            throw new InputException(__('Vui lòng nhập email hợp lệ.'));
        }

        if ($telephone === '') {
            return;
        }

        $this->assertPhoneAttributeReady();

        if (!$this->phoneNormalizer->isValid($telephone)) {
            throw new InputException(__('Vui lòng nhập số điện thoại hợp lệ.'));
        }

        $normalizedTelephone = $this->phoneNormalizer->normalize($telephone);
        if ($this->customerPhoneResolver->phoneExists($normalizedTelephone)) {
            throw new InputException(__('Số điện thoại này đã được sử dụng cho tài khoản khác.'));
        }

        $this->request->setParam('telephone', $normalizedTelephone);
        if (method_exists($this->request, 'setPostValue')) {
            $this->request->setPostValue('telephone', $normalizedTelephone);
        }
    }

    private function assertPhoneAttributeReady(): void
    {
        if (!$this->customerPhoneResolver->hasPhoneAttribute()) {
            throw new InputException(
                __('Tính năng đăng ký bằng số điện thoại chưa được cập nhật. Vui lòng thử lại sau.')
            );
        }
    }
}
