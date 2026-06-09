<?php

declare(strict_types=1);

namespace PeakGear\ContactWidget\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use PeakGear\ContactWidget\Model\Config;

class FloatingWidget extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function canRender(): bool
    {
        return $this->config->isEnabled() && $this->hasAnyAction();
    }

    public function hasPhoneAction(): bool
    {
        return $this->config->isPhoneEnabled() && $this->getPhoneNumber() !== '';
    }

    public function hasZaloAction(): bool
    {
        return $this->config->isZaloEnabled() && $this->getZaloUrl() !== '';
    }

    public function hasContactAction(): bool
    {
        return $this->config->isContactEnabled();
    }

    public function isCollapsedByDefault(): bool
    {
        return $this->config->isCollapsedByDefault();
    }

    public function getPhoneNumber(): string
    {
        return $this->config->getPhoneNumber();
    }

    public function getPhoneHref(): string
    {
        $phone = preg_replace('/(?!^\+)[^\d]/', '', $this->getPhoneNumber()) ?: '';
        if ($phone === '') {
            return '';
        }

        return 'tel:' . $phone;
    }

    public function getZaloUrl(): string
    {
        return $this->config->getZaloUrl();
    }

    public function getContactUrl(): string
    {
        return $this->getUrl('contact');
    }

    public function getWidgetClasses(): string
    {
        $classes = ['pg-contact-widget'];
        $classes[] = $this->isCollapsedByDefault() ? 'is-collapsed' : 'is-open';

        if (!$this->config->showOnMobile()) {
            $classes[] = 'pg-contact-widget--hide-mobile';
        }

        if (!$this->config->showOnDesktop()) {
            $classes[] = 'pg-contact-widget--hide-desktop';
        }

        return implode(' ', $classes);
    }

    public function getInlineStyle(): string
    {
        return sprintf(
            '--pg-contact-widget-bottom: %dpx; --pg-contact-widget-right: %dpx;',
            $this->config->getBottomOffset(),
            $this->config->getRightOffset()
        );
    }

    private function hasAnyAction(): bool
    {
        return $this->hasPhoneAction() || $this->hasZaloAction() || $this->hasContactAction();
    }
}
