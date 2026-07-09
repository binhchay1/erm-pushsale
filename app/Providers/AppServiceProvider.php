<?php

namespace App\Providers;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Events\OrderClosed;
use App\Listeners\DispatchShipmentOnOrderClosed;
use App\Repositories\EloquentOrderRepository;
use App\Services\Shipping\CarrierRegistry;
use App\Services\Shipping\Carriers\Ghn\GhnCarrier;
use App\Services\Shipping\Carriers\Ghtk\GhtkCarrier;
use App\Services\Shipping\Carriers\Jnt\JntCarrier;
use App\Services\Shipping\Carriers\Spx\SpxCarrier;
use App\Services\Shipping\Carriers\ViettelPost\ViettelPostCarrier;
use App\Services\Shipping\Support\PartnerCredentialResolver;
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
            return new CarrierRegistry([
                $app->make(GhtkCarrier::class),
                $app->make(GhnCarrier::class),
                $app->make(ViettelPostCarrier::class),
                $app->make(JntCarrier::class),
                $app->make(SpxCarrier::class),
            ], $app->make(PartnerCredentialResolver::class));
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
    }
}
