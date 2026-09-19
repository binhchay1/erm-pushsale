<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingCarrierInterface;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Settings\FeatureSettingsService;
use App\Services\Shipping\Gateways\NetShip\NetShipGateway;
use Illuminate\Validation\ValidationException;

class CreateShipmentService
{
    public function __construct(
        private readonly CarrierRegistry $registry,
        private readonly ShippingFeePresenter $feePresenter,
        private readonly FeatureSettingsService $featureSettings,
    ) {}

    public function createForOrder(Order $order, ?string $provider = null): Shipment
    {
        $order->loadMissing(['items', 'warehouse', 'company']);

        $providerKey = $provider
            ?? $order->shipping_provider
            ?? $order->company?->default_shipping_provider;

        if (! $providerKey || ! $this->registry->has($providerKey)) {
            $this->failBusiness(__('messages.shipping_actions.no_carrier_configured'));
        }

        $latestShipment = $order->shipments()
            ->where('provider', $providerKey)
            ->latest('id')
            ->first();

        $carrier = $this->registry->get($providerKey, $latestShipment);
        if (! $carrier->isReady()) {
            $label = (string) config("shipping_partners.providers.{$providerKey}.label", $providerKey);
            $netship = app(NetShipGateway::class);
            if ($netship->canProxy($providerKey) === false && array_key_exists($providerKey, $netship->routedProviders())) {
                $this->failBusiness(__('messages.shipping_actions.carrier_or_netship_not_ready', [
                    'carrier' => $label,
                ]));
            }
            $this->failBusiness(__('messages.shipping_actions.no_carrier_configured'));
        }

        if ($order->shipping_provider !== $providerKey) {
            $order->update([
                'shipping_provider' => $providerKey,
                'shipping_method' => $order->shipping_method
                    ?: $order->company?->default_shipping_method,
            ]);
        }

        return $carrier->createFromOrder($this->shipmentOrder($order));
    }

    private function shipmentOrder(Order $order): Order
    {
        $shipmentOrder = $order->fresh(['items', 'warehouse', 'company']);

        $fixedRecipientPhone = preg_replace('/\D+/', '', $this->featureSettings->string('SettingDangDonNguoiNhanSDT', ''));
        if ($fixedRecipientPhone !== '') {
            // Chỉ áp dụng cho payload gửi đơn vị giao vận, không ghi đè số khách trong hệ thống.
            $shipmentOrder->setAttribute('receiver_phone', $fixedRecipientPhone);
        }

        if (! filled($shipmentOrder->shipping_notes)) {
            $defaultNote = $this->featureSettings->string('SettingGhiChuGiaoHangSale', '');
            if (filled($defaultNote)) {
                $shipmentOrder->setAttribute('shipping_notes', $defaultNote);
            }
        }

        return $shipmentOrder;
    }

    public function sync(Order $order, ?string $provider = null): Shipment
    {
        $carrier = $this->carrierForOrder($order, $provider);

        return $carrier->syncStatus($order);
    }

    /** @return array<string, mixed> */
    public function calculateFee(Order $order, ?string $provider = null): array
    {
        $carrier = $this->carrierForOrder($order, $provider);
        $raw = $carrier->calculateFee($order->loadMissing(['items', 'warehouse']));

        return array_merge($raw, [
            'display' => $this->feePresenter->present($raw),
        ]);
    }

    public function cancel(Order $order, ?string $provider = null): Shipment
    {
        $carrier = $this->carrierForOrder($order, $provider);

        return $carrier->cancel($order);
    }

    /** @return array{success: bool, binary?: string, content_type?: string, message?: string} */
    public function printLabel(Order $order, ?string $provider = null): array
    {
        $carrier = $this->carrierForOrder($order, $provider);

        return $carrier->printLabel($order);
    }

    /** @return array<string, mixed> */
    public function runTest(string $provider, string $action): array
    {
        if ($provider === 'netship') {
            $routed = config('shipping_partners.providers.netship.routed_providers', []);
            $business = array_key_first($routed) ?: 'viettel_post';

            return $this->registry->netShipGateway()->proxyFor($business)->runTest(
                in_array($action, ['connection', 'provinces', 'test_connection'], true)
                    ? ($action === 'test_connection' ? 'connection' : $action)
                    : 'connection'
            );
        }

        return $this->registry->get($provider)->runTest($action);
    }

    private function carrierForOrder(Order $order, ?string $provider = null): ShippingCarrierInterface
    {
        $order->loadMissing('company');
        $key = $provider
            ?? $order->shipments()->latest('id')->value('provider')
            ?? $order->shipping_provider
            ?? $order->company?->default_shipping_provider;

        if (! $key || ! $this->registry->has($key)) {
            $this->failBusiness(__('messages.shipping_actions.carrier_undetermined'));
        }

        $shipment = $order->shipments()
            ->when($key, fn ($q) => $q->where('provider', $key))
            ->latest('id')
            ->first();

        return $this->registry->get($key, $shipment);
    }

    private function failBusiness(string $message): never
    {
        throw ValidationException::withMessages([
            'shipping' => [$message],
        ]);
    }
}
