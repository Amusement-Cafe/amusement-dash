<?php

namespace App\Providers;

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
        \Illuminate\Support\Facades\Event::listen(
            \SocialiteProviders\Manager\SocialiteWasCalled::class,
            [\SocialiteProviders\Discord\DiscordExtendSocialite::class, 'handle']
        );

        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            $apiIsDown = \Illuminate\Support\Facades\Cache::remember('api_is_down', 5, function () {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(1)->get(env('AMUSE_API_ROOT', 'http://127.0.0.1:2727') . '/health');
                    return !$response->successful();
                } catch (\Exception $e) {
                    return true;
                }
            });
            $view->with('apiIsDown', $apiIsDown);
        });
    }
}
