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
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
    }
}
