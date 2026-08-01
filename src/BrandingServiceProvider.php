<?php

namespace ConferenceTools\Branding;

use ConferenceTools\Branding\Contracts\BrandingProvider;
use ConferenceTools\Branding\Services\BrandingService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/** Boots the branding package inside the host: config, migrations, routes, views, translations, and the real BrandingProvider binding. */
class BrandingServiceProvider extends ServiceProvider
{
    private const VIEW_NAMESPACE = 'branding';

    /** Merge the package config and bind BrandingProvider to the real BrandingService. */
    public function register(): void
    {
        // Published as config/branding.php; the config key stays "branding".
        $this->mergeConfigFrom(__DIR__.'/../config/branding.php', 'branding');

        // Branding is host-owned management. Bind the real implementation to the
        // shared BrandingProvider seam so the consuming packages' views (and the
        // host layout) render the host's configured branding. Registered after
        // the consuming packages so this binding wins over their neutral
        // DefaultBranding fallbacks.
        $this->app->singleton(BrandingService::class);
        $this->app->singleton(BrandingProvider::class, fn ($app) => $app->make(BrandingService::class));
    }

    /** Load the package's migrations, routes, views, and translations, and register publishing. */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', self::VIEW_NAMESPACE);
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', self::VIEW_NAMESPACE);
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Expose a `$branding` object and the configured layout name to the
        // package's own management view, and a Blade-accessible mirror of
        // Controller::routeName() so the view builds route names through the
        // configured prefix instead of hard-coding it.
        View::composer(self::VIEW_NAMESPACE.'::*', function ($view): void {
            $view->with('branding', $this->app->make(BrandingProvider::class));
            $view->with('brandingLayout', config('branding.layout'));
            $view->with('routeName', fn (string $name): string => config('branding.route_name_prefix').$name);
        });

        $this->registerPublishing();
    }

    /** Expose the package's config, views, translations, and stylesheets to artisan vendor:publish. */
    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // The shared iccm-* stylesheets, which the host layout links and every
        // conference-tools package's markup depends on. Unlike the tags below
        // this one is not optional — without it every screen renders unstyled —
        // so it also carries Laravel's conventional "laravel-assets" tag, which
        // the stock post-update-cmd composer script republishes with --force.
        $this->publishes([
            __DIR__.'/../resources/css' => public_path('vendor/branding/css'),
        ], ['branding-assets', 'laravel-assets']);

        $this->publishes([
            __DIR__.'/../config/branding.php' => config_path('branding.php'),
        ], 'branding-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/'.self::VIEW_NAMESPACE),
        ], 'branding-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->langPublishPath(),
        ], 'branding-lang');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'branding-migrations');
    }

    /** The host path package translations publish to. */
    private function langPublishPath(): string
    {
        return function_exists('lang_path')
            ? lang_path('vendor/'.self::VIEW_NAMESPACE)
            : resource_path('lang/vendor/'.self::VIEW_NAMESPACE);
    }
}
