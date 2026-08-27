<?php

namespace App\Providers;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Events\OrderClosed;
use App\Listeners\DispatchShipmentOnOrderClosed;
use App\Models\CarrierSettlementLine;
use App\Models\InboundEvent;
use App\Models\LandingConnection;
use App\Models\LeadIngestion;
use App\Models\MarketingSourceDailyMetric;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\Team;
use App\Models\ShippingStatusEvent;
use App\Models\ShippingWebhookEvent;
use App\Models\WarehouseInventoryMovement;
use App\Models\WarehouseReturnReceipt;
use App\Models\WarehouseReturnReceiptLine;
use App\Models\User;
use App\Observers\ReportAccessScopeObserver;
use App\Observers\ReportDateObserver;
use App\Observers\InboundEventObserver;
use App\Observers\LeadIngestionObserver;
use App\Observers\OrderObserver;
use App\Repositories\EloquentOrderRepository;
use App\Services\Shipping\CarrierRegistry;
use App\Services\Shipping\Carriers\Generic\GenericCarrier;
use App\Services\Shipping\Carriers\Ghn\GhnCarrier;
use App\Services\Shipping\Carriers\Ghtk\GhtkCarrier;
use App\Services\Shipping\Carriers\Jnt\JntCarrier;
use App\Services\Shipping\Carriers\Manual\ManualCarrier;
use App\Services\Shipping\Carriers\Spx\SpxCarrier;
use App\Services\Shipping\Carriers\ViettelPost\ViettelPostCarrier;
use App\Services\Shipping\Gateways\NetShip\NetShipGateway;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Support\ShippingProviders;
use App\Support\Seo;
use App\Support\TenantManager;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
        $this->app->singleton(Seo::class);

        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);

        $this->app->singleton(CarrierRegistry::class, function ($app) {
            $resolver = $app->make(PartnerCredentialResolver::class);
            $carriers = [
                $app->make(ManualCarrier::class),
                $app->make(GhtkCarrier::class),
                $app->make(GhnCarrier::class),
                $app->make(ViettelPostCarrier::class),
                $app->make(JntCarrier::class),
                $app->make(SpxCarrier::class),
            ];

            $direct = ['manual', 'ghtk', 'ghn', 'viettel_post', 'jnt', 'spx', 'netship'];
            foreach (array_keys(config('shipping_partners.providers', [])) as $provider) {
                if (in_array($provider, $direct, true) || ShippingProviders::isGateway($provider)) {
                    continue;
                }
                $carriers[] = new GenericCarrier($provider, $resolver);
            }

            return new CarrierRegistry(
                $carriers,
                $resolver,
                $app->make(NetShipGateway::class),
            );
        });
    }

    public function boot(): void
    {
        App::setLocale(config('app.locale'));
        Carbon::setLocale(config('app.locale'));

        Event::listen(
            OrderClosed::class,
            DispatchShipmentOnOrderClosed::class,
        );

        foreach ([User::class, Team::class, \App\Models\MarketingSource::class] as $scopeModel) {
            $scopeModel::observe(ReportAccessScopeObserver::class);
        }

        Order::observe(OrderObserver::class);
        LeadIngestion::observe(LeadIngestionObserver::class);
        InboundEvent::observe(InboundEventObserver::class);

        foreach ([
            Order::class,
            OrderItem::class,
            LeadIngestion::class,
            InboundEvent::class,
            Shipment::class,
            ShippingStatusEvent::class,
            ShippingWebhookEvent::class,
            CarrierSettlementLine::class,
            MarketingSourceDailyMetric::class,
            WarehouseInventoryMovement::class,
            WarehouseReturnReceipt::class,
            WarehouseReturnReceiptLine::class,
            LandingConnection::class,
        ] as $reportingModel) {
            $reportingModel::observe(ReportDateObserver::class);
        }


        // Chống flood/spam cổng nhận lead — giới hạn theo token chiến dịch + IP.
        RateLimiter::for('lead-intake', function (Request $request) {
            $perMinute = (int) config('saleops.lead_intake.rate_limit_per_minute', 60);
            $key = $request->route('token')
                ?? $request->route('platform')
                ?? $request->ip();

            return Limit::perMinute(max(1, $perMinute))->by($key.'|'.$request->ip());
        });


        RateLimiter::for('api-auth', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });



        RateLimiter::for('pancake-chat-webhook', function (Request $request) {
            $key = $request->route('token') ?: $request->ip();

            return Limit::perMinute((int) config('saleops.pancake_chat.webhook_rate_limit_per_minute', 120))
                ->by($key.'|'.$request->ip());
        });

        RateLimiter::for('extension-intake', function (Request $request) {
            $userId = $request->user()?->id ?: 'guest';

            return Limit::perMinute(120)->by($userId.'|'.$request->ip());
        });

        RateLimiter::for('warehouse-excel-export', function (Request $request) {
            $userId = $request->user()?->id ?: 'guest';
            $perMinute = max(1, (int) config('warehouse_excel_export.throttle_per_minute', 3));

            return Limit::perMinute($perMinute)->by($userId.'|'.$request->ip());
        });
    }
}
