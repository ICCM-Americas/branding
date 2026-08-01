<?php

namespace ConferenceTools\Branding\Tests\Unit;

use ConferenceTools\Branding\Models\Setting;
use ConferenceTools\Branding\Services\BrandingService;
use ConferenceTools\Branding\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/** Unit tests for BrandingService. */
#[TestDox('BrandingService')]
class BrandingServiceTest extends TestCase
{
    use RefreshDatabase;

    /** The branding provider under test. */
    private function branding(): BrandingService
    {
        return app(BrandingService::class);
    }

    #[Test]
    #[TestDox('falls back to the app name when no site name is configured')]
    public function site_name_falls_back_to_app_name(): void
    {
        config(['app.name' => 'Fallback App']);

        $this->assertSame('Fallback App', $this->branding()->siteName());
    }

    #[Test]
    #[TestDox('uses the configured site name when present')]
    public function site_name_uses_configured_value(): void
    {
        Setting::put('branding.site_name', 'My Event');

        $this->assertSame('My Event', $this->branding()->siteName());
    }

    #[Test]
    #[TestDox('short name keeps names of 12 characters or fewer intact')]
    public function short_name_keeps_short_names(): void
    {
        Setting::put('branding.site_name', 'Short Name');

        $this->assertSame('Short Name', $this->branding()->shortName());
    }

    #[Test]
    #[TestDox('short name truncates long names to 12 characters')]
    public function short_name_truncates_long_names(): void
    {
        Setting::put('branding.site_name', 'A Very Long Event Name');

        $this->assertSame('A Very Long ', $this->branding()->shortName());
    }

    #[Test]
    #[TestDox('returns the default color when none is configured')]
    public function color_returns_default(): void
    {
        $this->assertSame(BrandingService::DEFAULTS['primary_color'], $this->branding()->color('primary'));
    }

    #[Test]
    #[TestDox('returns a generic black for an unknown color key')]
    public function color_returns_black_for_unknown_key(): void
    {
        $this->assertSame('#000000', $this->branding()->color('nonexistent'));
    }

    #[Test]
    #[TestDox('returns the configured color when present')]
    public function color_returns_configured_value(): void
    {
        Setting::put('branding.primary_color', '#123456');

        $this->assertSame('#123456', $this->branding()->color('primary'));
    }

    #[Test]
    #[TestDox('logo URL is null when no logo is stored')]
    public function logo_url_is_null_without_logo(): void
    {
        $this->assertFalse($this->branding()->hasLogo());
        $this->assertNull($this->branding()->logoMime());
        $this->assertNull($this->branding()->logoUrl());
    }

    #[Test]
    #[TestDox('logo URL is a data URI built from the stored base64 data and mime')]
    public function logo_url_built_from_stored_data(): void
    {
        $data = base64_encode('fake-image-bytes');
        Setting::put('branding.logo_data', $data);
        Setting::put('branding.logo_mime', 'image/png');

        $this->assertTrue($this->branding()->hasLogo());
        $this->assertSame('image/png', $this->branding()->logoMime());
        $this->assertSame('data:image/png;base64,'.$data, $this->branding()->logoUrl());
    }

    #[Test]
    #[TestDox('logo mime defaults to PNG when only data is stored')]
    public function logo_mime_defaults_to_png(): void
    {
        Setting::put('branding.logo_data', base64_encode('bytes'));

        $this->assertSame('image/png', $this->branding()->logoMime());
    }

    #[Test]
    #[TestDox('a selected theme provides a bundled logo data URI')]
    public function theme_provides_bundled_logo(): void
    {
        Setting::put('branding.theme', 'asia');

        $branding = $this->branding();
        $this->assertNotNull($branding->themeLogoPath('asia'));
        $this->assertStringStartsWith('data:image/png;base64,', (string) $branding->themeLogoUrl('asia'));
        // With no upload, the logo resolves to the theme's bundled one.
        $this->assertTrue($branding->hasLogo());
        $this->assertFalse($branding->hasUploadedLogo());
        $this->assertSame('image/png', $branding->logoMime());
        $this->assertSame($branding->themeLogoUrl('asia'), $branding->logoUrl());
    }

    #[Test]
    #[TestDox('every preset theme bundles a logo file')]
    public function every_theme_bundles_a_logo(): void
    {
        $branding = $this->branding();

        foreach (array_keys($branding->themes()) as $slug) {
            $this->assertNotNull($branding->themeLogoPath($slug), "Theme {$slug} is missing its bundled logo");
        }
    }

    #[Test]
    #[TestDox('the custom theme has no bundled logo')]
    public function custom_theme_has_no_logo(): void
    {
        $this->assertNull($this->branding()->themeLogoPath('custom'));
        $this->assertNull($this->branding()->themeLogoUrl('custom'));
    }

    #[Test]
    #[TestDox('every theme carries a hard-coded logo color')]
    public function every_theme_carries_a_logo_color(): void
    {
        $branding = $this->branding();

        foreach (array_keys($branding->themes()) as $slug) {
            $this->assertMatchesRegularExpression(
                '/^#[0-9a-f]{6}$/',
                (string) $branding->themeLogoColor($slug),
                "Theme {$slug} is missing its logo color"
            );
        }
    }

    #[Test]
    #[TestDox('logo color falls back to an automatic contrast color when unset')]
    public function logo_color_falls_back_to_contrast(): void
    {
        Setting::put('branding.primary_color', '#2c0c57'); // dark -> white contrast

        $this->assertSame('#ffffff', $this->branding()->logoColor());
    }

    #[Test]
    #[TestDox('logo color uses the configured value when present')]
    public function logo_color_uses_configured_value(): void
    {
        Setting::put('branding.logo_color', '#123456');

        $this->assertSame('#123456', $this->branding()->logoColor());
    }

    #[Test]
    #[TestDox('an uploaded logo overrides the theme logo')]
    public function uploaded_logo_overrides_theme_logo(): void
    {
        Setting::put('branding.theme', 'asia');
        $data = base64_encode('fake-image-bytes');
        Setting::put('branding.logo_data', $data);
        Setting::put('branding.logo_mime', 'image/webp');

        $branding = $this->branding();
        $this->assertTrue($branding->hasUploadedLogo());
        $this->assertSame('image/webp', $branding->logoMime());
        $this->assertSame('data:image/webp;base64,'.$data, $branding->logoUrl());
    }

    #[Test]
    #[TestDox('theme defaults to custom when none is configured')]
    public function theme_defaults_to_custom(): void
    {
        $this->assertSame('custom', $this->branding()->theme());
    }

    #[Test]
    #[TestDox('theme returns the configured preset when it is a known theme')]
    public function theme_returns_configured_preset(): void
    {
        Setting::put('branding.theme', 'asia');

        $this->assertSame('asia', $this->branding()->theme());
    }

    #[Test]
    #[TestDox('theme falls back to custom for an unknown stored value')]
    public function theme_falls_back_to_custom_for_unknown(): void
    {
        Setting::put('branding.theme', 'atlantis');

        $this->assertSame('custom', $this->branding()->theme());
    }

    #[Test]
    #[TestDox('every preset theme is well formed')]
    public function themes_are_well_formed(): void
    {
        $this->assertNotEmpty($this->branding()->themes());

        foreach ($this->branding()->themes() as $slug => $theme) {
            $this->assertIsString($slug);
            $this->assertArrayHasKey('label', $theme);
            foreach (['primary_color', 'secondary_color', 'background_color', 'text_color'] as $key) {
                $this->assertArrayHasKey($key, $theme);
                $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $theme[$key]);
            }
        }
    }

    #[Test]
    #[TestDox('contrast color is dark on light backgrounds and white on dark ones')]
    public function contrast_color_picks_legible_foreground(): void
    {
        $branding = $this->branding();

        // Light surfaces -> dark text.
        $this->assertSame('#0f172a', $branding->contrastColor('#ffffff'));
        $this->assertSame('#0f172a', $branding->contrastColor('#e5ba22')); // Asia gold
        $this->assertSame('#0f172a', $branding->contrastColor('#fff')); // 3-digit shorthand

        // Dark surfaces -> white text.
        $this->assertSame('#ffffff', $branding->contrastColor('#000000'));
        $this->assertSame('#ffffff', $branding->contrastColor('#2c0c57')); // Europe purple
        $this->assertSame('#ffffff', $branding->contrastColor('#363839')); // Americas charcoal
    }

    #[Test]
    #[TestDox('contrast color falls back to white for malformed input')]
    public function contrast_color_handles_malformed_input(): void
    {
        $this->assertSame('#ffffff', $this->branding()->contrastColor('not-a-color'));
    }

    #[Test]
    #[TestDox('onColor reads the configured surface color and returns its contrast')]
    public function on_color_uses_configured_surface(): void
    {
        Setting::put('branding.primary_color', '#e5ba22'); // gold

        $this->assertSame('#0f172a', $this->branding()->onColor('primary'));
    }

    #[Test]
    #[TestDox('all() returns a complete branding bundle')]
    public function all_returns_complete_bundle(): void
    {
        $all = $this->branding()->all();

        foreach (['site_name', 'logo_url', 'primary_color', 'secondary_color', 'background_color', 'text_color'] as $key) {
            $this->assertArrayHasKey($key, $all);
        }
    }
}
