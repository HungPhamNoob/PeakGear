<?php
declare(strict_types=1);

namespace Vendor\Shipping\Service;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Vendor\Shipping\Model\Config;

class RequestPayloadBuilder
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly Config $config
    ) {
    }

    public function build(RateRequest $request, int $weightGram, ?int $storeId = null): array
    {
        $payload = [
            'pick_province' => $this->config->getPickProvince($storeId),
            'pick_district' => $this->config->getPickDistrict($storeId),
            'province' => $this->resolveProvince($request),
            'district' => $this->resolveDistrict($request),
            'address' => $this->resolveAddress($request),
            'weight' => $weightGram,
            'value' => max(0, (int)round((float)($request->getPackageValueWithDiscount() ?: $request->getPackageValue()))),
            'transport' => $this->config->getTransport($storeId),
        ];

        $pickAddressId = trim($this->config->getPickAddressId($storeId));
        if ($pickAddressId !== '') {
            $payload['pick_address_id'] = $pickAddressId;
        }

        $pickAddress = trim($this->config->getPickAddress($storeId));
        if ($pickAddress !== '') {
            $payload['pick_address'] = $pickAddress;
        }

        $pickWard = trim($this->config->getPickWard($storeId));
        if ($pickWard !== '') {
            $payload['pick_ward'] = $pickWard;
        }

        $ward = $this->resolveWard();
        if ($ward !== '') {
            $payload['ward'] = $ward;
        }

        return $payload;
    }

    private function resolveProvince(RateRequest $request): string
    {
        $province = trim((string)$request->getDestRegionCode());
        if ($province !== '') {
            return $province;
        }

        $quoteAddress = $this->getQuoteShippingAddress();

        return $quoteAddress ? trim((string)$quoteAddress->getRegion()) : '';
    }

    private function resolveDistrict(RateRequest $request): string
    {
        $district = trim((string)$request->getDestCity());
        if ($district !== '') {
            return $district;
        }

        $quoteAddress = $this->getQuoteShippingAddress();

        return $quoteAddress ? trim((string)$quoteAddress->getCity()) : '';
    }

    private function resolveAddress(RateRequest $request): string
    {
        $address = trim((string)$request->getDestStreet());
        if ($address !== '') {
            return $address;
        }

        $quoteAddress = $this->getQuoteShippingAddress();

        return $quoteAddress ? trim((string)$quoteAddress->getStreetFull()) : '';
    }

    private function resolveWard(): string
    {
        $quoteAddress = $this->getQuoteShippingAddress();

        return $quoteAddress ? trim((string)$quoteAddress->getData('ward')) : '';
    }

    private function getQuoteShippingAddress(): ?Address
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            return null;
        }

        $address = $quote->getShippingAddress();

        return $address instanceof Address ? $address : null;
    }
}
