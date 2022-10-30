<?php

namespace App\Providers;

use App\Helpers\IpAddressBanManager;
use Laravel\Octane\Facades\Octane;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Response;
use Illuminate\Database\Schema\Blueprint;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (!$this->app->environment('production'))
        {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);

            $this->app->register(\Laravel\Horizon\HorizonServiceProvider::class);
            $this->app->register(HorizonServiceProvider::class);
        }
    }

    /**
     * I'm going to blow my head off
     * 
     * @return void
     */
    protected function schema()
    {
        Blueprint::macro('packed', function ($key) {
            return $this->binary($key);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapFive();

        Blade::anonymousComponentNamespace('admin/components', 'admin');
        Blade::anonymousComponentNamespace('my/components', 'account');

        Blade::if('may', function ($roleset, $role) {
            /** @var \App\Models\User */
            $user = auth()->user();

            return $user->may($roleset, $role);
        });

        Response::macro('text', function ($text) {
            return Response::make($text, 200, ['Content-Type' => 'text/plain']);
        });

        Response::macro('pack', function ($data) {
            return Response::make(msgpack_pack($data), 200, ['Content-Type' => 'application/x-msgpack']);
        });

        AppServiceProvider::schema();

        if (!app()->runningInConsole() && $this->app->environment('production'))
        {
            Octane::tick('ip-address-ban-refresher', fn() => IpAddressBanManager::refresh())
                ->seconds(60 * 30)
                ->immediate();
        }
    }
}
