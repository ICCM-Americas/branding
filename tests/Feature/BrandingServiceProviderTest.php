<?php

namespace ConferenceTools\Branding\Tests\Feature;

use ConferenceTools\Branding\BrandingServiceProvider;
use ConferenceTools\Branding\Tests\TestCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/** Feature tests for Branding Service Provider. */
#[TestDox('Branding Service Provider')]
class BrandingServiceProviderTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function publishTags(): iterable
    {
        yield 'config' => ['branding-config'];
        yield 'views' => ['branding-views'];
        yield 'lang' => ['branding-lang'];
        yield 'migrations' => ['branding-migrations'];
        yield 'assets' => ['branding-assets'];
        // The shared stylesheets also join Laravel's conventional group, so the
        // stock post-update-cmd composer script republishes them.
        yield 'laravel assets' => ['laravel-assets'];
    }

    #[TestDox('publish groups are registered in console')]
    #[DataProvider('publishTags')]
    public function test_publish_groups_are_registered_in_console(string $tag): void
    {
        $this->assertNotEmpty(
            ServiceProvider::pathsToPublish(BrandingServiceProvider::class, $tag),
            "Missing publish group: {$tag}"
        );
    }

    #[TestDox('the shared stylesheets publish into the web root')]
    public function test_the_shared_stylesheets_publish_into_the_web_root(): void
    {
        $paths = ServiceProvider::pathsToPublish(BrandingServiceProvider::class, 'branding-assets');
        $source = realpath(__DIR__.'/../../resources/css');

        $this->assertContains(public_path('vendor/branding/css'), $paths);
        $this->assertContains($source, array_map('realpath', array_keys($paths)));

        // The host layout links these two by name; renaming one silently
        // unstyles every screen, so pin the filenames here.
        $this->assertFileExists($source.'/iccm.css');
        $this->assertFileExists($source.'/iccm-utilities.css');
    }

    #[TestDox('publishing is skipped when not running in console')]
    public function test_publishing_is_skipped_when_not_running_in_console(): void
    {
        $app = \Mockery::mock(Application::class);
        $app->shouldReceive('runningInConsole')->andReturnFalse();

        // Outside the console the guard must short-circuit before registering any
        // publish group: shouldNotReceive throws the moment publishes() is hit.
        $provider = \Mockery::mock(BrandingServiceProvider::class.'[publishes]', [$app])
            ->shouldAllowMockingProtectedMethods();
        $provider->shouldNotReceive('publishes');

        $method = (new \ReflectionClass(BrandingServiceProvider::class))->getMethod('registerPublishing');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertFalse($app->runningInConsole());
    }
}
