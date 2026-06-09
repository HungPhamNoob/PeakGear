<?php
declare(strict_types=1);

namespace PeakGear\Account\Plugin\OrderCancellation;

use Magento\OrderCancellation\Model\CustomerCanCancel;
use Magento\Sales\Model\Order;
use PeakGear\Account\Setup\Patch\Data\AddCancellationRequestedStatus;
use PHPUnit\Framework\TestCase;

class PreventDuplicateRequestPluginTest extends TestCase
{
    public function testRequestedOrderCannotSubmitAgain(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn(AddCancellationRequestedStatus::STATUS);

        $plugin = new PreventDuplicateRequestPlugin();

        self::assertFalse(
            $plugin->afterExecute($this->createMock(CustomerCanCancel::class), true, $order)
        );
    }
}
