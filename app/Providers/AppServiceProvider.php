<?php

namespace App\Providers;

use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (Request::hasHeader('X-Forwarded-Host')) {
            Debugbar::disable();

            return;
        }

        $allowedIPs = array_map('trim', explode(',', config('app.debug_allowed_ips', '')));

        $allowedIPs = array_filter($allowedIPs);

        if (empty($allowedIPs)) {
            return;
        }

        if (in_array(Request::ip(), $allowedIPs)) {
            Debugbar::enable();
        } else {
            Debugbar::disable();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ParallelTesting::setUpTestDatabase(function (string $database, int $token) {
            Artisan::call('db:seed');
        });

        $this->useRequestHostForPublicStorageUrls();
    }

    /**
     * Build public storage URLs from the incoming request's host instead of APP_URL,
     * so images keep working when the site is reached through a tunnel or another domain.
     */
    protected function useRequestHostForPublicStorageUrls(): void
    {
        Event::listen(RouteMatched::class, function (RouteMatched $event) {
            config(['filesystems.disks.public.url' => $event->request->getSchemeAndHttpHost().'/storage']);

            Storage::forgetDisk('public');
        });
    }
}
