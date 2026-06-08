<?php
declare(strict_types=1);

namespace PeakGear\Cart\Model;

use Magento\Framework\Session\SessionManagerInterface;

class BuyNowSession
{
    private const SNAPSHOT_KEY = 'peakgear_buy_now_snapshot_items';
    private const TEMP_QUOTE_ID_KEY = 'peakgear_buy_now_temporary_quote_id';

    public function __construct(
        private readonly SessionManagerInterface $session
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSnapshotItems(): array
    {
        $items = $this->session->getData(self::SNAPSHOT_KEY);

        return is_array($items) ? $items : [];
    }

    public function getTemporaryQuoteId(): ?int
    {
        $quoteId = $this->session->getData(self::TEMP_QUOTE_ID_KEY);

        if ($quoteId === null || $quoteId === '') {
            return null;
        }

        $quoteId = (int) $quoteId;

        return $quoteId > 0 ? $quoteId : null;
    }

    public function hasPendingBuyNow(): bool
    {
        return $this->getTemporaryQuoteId() !== null;
    }

    /**
     * Persist the pre-buy-now cart snapshot. If the active temporary quote is the
     * same one we already track, keep the original snapshot intact.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function rememberOriginalCart(array $items, int $temporaryQuoteId): void
    {
        $existingTemporaryQuoteId = $this->getTemporaryQuoteId();

        if ($existingTemporaryQuoteId !== null && $existingTemporaryQuoteId === $temporaryQuoteId) {
            return;
        }

        $this->session->setData(self::SNAPSHOT_KEY, $items);
        $this->session->setData(self::TEMP_QUOTE_ID_KEY, $temporaryQuoteId);
    }

    public function clear(): void
    {
        $this->session->unsetData(self::SNAPSHOT_KEY);
        $this->session->unsetData(self::TEMP_QUOTE_ID_KEY);
    }
}
