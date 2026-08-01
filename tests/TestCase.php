<?php

namespace ConferenceTools\Branding\Tests;

use ConferenceTools\Branding\BrandingServiceProvider;
use ConferenceTools\Branding\Tests\Fixtures\User;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Gate;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    // Verify Mockery expectations (->once(), ->shouldNotReceive(), ...) at
    // teardown and count them as assertions, so mock-based tests are enforced.
    use MockeryPHPUnitIntegration;

    protected function getPackageProviders($app): array
    {
        return [
            BrandingServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app->make('config'), function (Repository $config): void {
            // The web middleware (sessions/cookies/encryption) needs an app key.
            $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

            // The host owns users; point the auth guard at the test fixture model.
            $config->set('auth.providers.users.model', User::class);

            // Render the branding screen against the package's bundled fallback
            // layout (no host layout exists in the isolated test app).
            $config->set('branding.layout', 'branding::layouts.app');

            // In-memory sqlite for speed.
            $config->set('database.default', 'testing');
            $config->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        });

        // The host decides who may administer branding; here, any admin user.
        Gate::define('manage-branding', fn (User $user) => $user->isAdmin());
    }

    protected function defineDatabaseMigrations(): void
    {
        // A representative host users table; the branding_settings table is loaded
        // by the service provider.
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /** An admin user. */
    protected function createAdmin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    /** A regular (non-admin) user. */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }
}
