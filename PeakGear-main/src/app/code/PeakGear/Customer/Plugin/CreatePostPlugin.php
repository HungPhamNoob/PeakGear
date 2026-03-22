<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\RequestInterface;

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
        RequestInterface $request
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
    }
}
