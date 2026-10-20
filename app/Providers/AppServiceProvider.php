<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

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
        if (app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

         FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_START,
            fn (): string => session()->has('impersonator_id') ? view('impersonation-banner')->render() : '',
        );

        // makes the panel installable as an app (PWA)
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, fn (): string => view('pwa-head')->render());
        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, fn (): string => view('pwa-register')->render());
    }
}
