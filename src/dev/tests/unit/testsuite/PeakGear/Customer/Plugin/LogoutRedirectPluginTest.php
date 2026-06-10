<?php
declare(strict_types=1);

namespace PeakGear\Customer\Plugin;

use Magento\Customer\Controller\Account\Logout;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class LogoutRedirectPluginTest extends TestCase
{
    public function testLogoutRedirectsDirectlyToHome(): void
    {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->expects(self::once())
            ->method('getUrl')
            ->with('')
            ->willReturn('https://peakgear.test/');

        $result = $this->createMock(Redirect::class);
        $result->expects(self::once())
            ->method('setUrl')
            ->with('https://peakgear.test/')
            ->willReturnSelf();

        $plugin = new LogoutRedirectPlugin($urlBuilder);

        self::assertSame(
            $result,
            $plugin->afterExecute($this->createMock(Logout::class), $result)
        );
    }
}
