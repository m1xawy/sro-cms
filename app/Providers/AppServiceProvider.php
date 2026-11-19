<?php

namespace App\Providers;

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        Notification::extend('whatsapp', fn ($app) => $app->make(WhatsAppChannel::class));
    }
}
