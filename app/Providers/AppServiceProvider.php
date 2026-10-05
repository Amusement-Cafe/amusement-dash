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

        // Re-check page toggles on Livewire actions too, so a page turned off
        // while someone has it open stops accepting requests (e.g. plot collect).
        \Livewire\Livewire::addPersistentMiddleware([
            \App\Http\Middleware\EnsurePageEnabled::class,
        ]);

        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            $apiIsDown = \Illuminate\Support\Facades\Cache::remember('api_is_down', 5, function () {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(1)->get(config('services.amuse.api_root') . '/health');
                    return !$response->successful();
                } catch (\Exception $e) {
                    return true;
                }
            });
            $view->with('apiIsDown', $apiIsDown);
        });
    }
}
