<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin\SocialLogin;

use Hybridauth\Storage\Session as HybridAuthSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Mageplaza\SocialLogin\Model\Social;

class SocialPlugin
{
    private const PROVIDERS = [
        'google',
        'twitter',
        'yahoo',
        'linkedin',
        'amazon',
        'github',
        'foursquare',
        'facebook',
        'instagram',
        'live',
        'vkontakte',
        'zalo',
        'pinterest',
    ];

    public function __construct(
        private readonly HybridAuthSession $hybridAuthSession,
        private readonly RequestInterface $request
    ) {
    }

    public function aroundGetProviderConnected(Social $subject, callable $proceed): string
    {
        $provider = $this->resolveProviderFromRequest() ?: $this->resolveProviderFromState();
        if ($provider) {
            return $provider;
        }

        if (!$this->getRemoteState()) {
            throw new NoSuchEntityException(__('Unknown Provider'));
        }

        try {
            return (string) $proceed();
        } catch (NoSuchEntityException $exception) {
            throw $exception;
        }
    }

    private function resolveProviderFromRequest(): ?string
    {
        $provider = (string) ($this->request->getParam('hauth_done') ?: $this->request->getParam('hauth.done'));
        if ($provider === '') {
            return null;
        }

        return strtolower($provider);
    }

    private function resolveProviderFromState(): ?string
    {
        $stateRemote = $this->getRemoteState();
        if ($stateRemote === '') {
            return null;
        }

        foreach (self::PROVIDERS as $provider) {
            $requestToken = (string) $this->hybridAuthSession->get($provider . '.request_token');
            $authorizationState = (string) $this->hybridAuthSession->get($provider . '.authorization_state');

            if ($requestToken === $stateRemote || $authorizationState === $stateRemote) {
                return $provider;
            }
        }

        return null;
    }

    private function getRemoteState(): string
    {
        return (string) ($this->request->getParam('oauth_token') ?: $this->request->getParam('state'));
    }
}
