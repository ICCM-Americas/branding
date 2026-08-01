<?php

namespace ConferenceTools\Branding\Tests\Feature;

use ConferenceTools\Branding\Models\Setting;
use ConferenceTools\Branding\Services\BrandingService;
use ConferenceTools\Branding\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/** Feature tests for Admin branding settings. */
#[TestDox('Admin branding settings')]
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('admin can view the branding settings page')]
    public function admin_can_view_settings(): void
    {
        $this->actingAs($this->createAdmin())->get(route('admin.branding.edit'))->assertOk();
    }

    #[Test]
    #[TestDox('admin can update branding name and colors')]
    public function admin_can_update_branding(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'site_name' => 'My Event',
            'primary_color' => '#123456',
            'secondary_color' => '#abcdef',
            'background_color' => '#ffffff',
            'text_color' => '#000000',
        ])->assertRedirect(route('admin.branding.edit'));

        $this->assertSame('My Event', Setting::get('branding.site_name'));
        $this->assertSame('#123456', Setting::get('branding.primary_color'));
    }

    #[Test]
    #[TestDox('admin can set a custom logo/title link that overrides the theme URL')]
    public function admin_can_set_custom_url(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'theme' => 'europe',
            'url' => 'https://example.org/conference',
            'primary_color' => '#2c0c57', 'secondary_color' => '#37abc8',
        ])->assertRedirect(route('admin.branding.edit'));

        $this->assertSame('https://example.org/conference', Setting::get('branding.url'));
        $this->assertSame('https://example.org/conference', app(BrandingService::class)->url());
    }

    #[Test]
    #[TestDox('a blank link falls back to the selected theme\'s conference URL')]
    public function blank_url_falls_back_to_theme_url(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'theme' => 'europe',
            'primary_color' => '#2c0c57', 'secondary_color' => '#37abc8',
        ])->assertRedirect();

        $this->assertNull(Setting::get('branding.url'));
        $this->assertSame(BrandingService::THEMES['europe']['url'], app(BrandingService::class)->url());
    }

    #[Test]
    #[TestDox('a malformed link URL is rejected')]
    public function malformed_url_rejected(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'url' => 'not a url',
            'primary_color' => '#123456', 'secondary_color' => '#abcdef',
        ])->assertSessionHasErrors('url');
    }

    #[Test]
    #[TestDox('admin can upload a logo, stored as base64 in the database')]
    public function admin_can_upload_logo(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'primary_color' => '#123456',
            'secondary_color' => '#abcdef',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect();

        $data = Setting::get('branding.logo_data');
        $this->assertNotNull($data);
        $this->assertNotFalse(base64_decode($data, true));
        $this->assertSame('image/png', Setting::get('branding.logo_mime'));
    }

    #[Test]
    #[TestDox('uploading a new logo replaces the previous one')]
    public function uploading_logo_replaces_old(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'primary_color' => '#123456', 'secondary_color' => '#abcdef',
            'logo' => UploadedFile::fake()->image('first.png', 10, 10),
        ]);
        $old = Setting::get('branding.logo_data');

        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'primary_color' => '#123456', 'secondary_color' => '#abcdef',
            'logo' => UploadedFile::fake()->image('second.png', 40, 40),
        ]);
        $new = Setting::get('branding.logo_data');

        $this->assertNotNull($old);
        $this->assertNotNull($new);
        $this->assertNotSame($old, $new);
    }

    #[Test]
    #[TestDox('admin can select a preset color theme')]
    public function admin_can_select_theme(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'theme' => 'europe',
            'primary_color' => '#2c0c57',
            'secondary_color' => '#37abc8',
            'background_color' => '#e6e6e6',
            'text_color' => '#0f172a',
        ])->assertRedirect(route('admin.branding.edit'));

        $this->assertSame('europe', Setting::get('branding.theme'));
    }

    #[Test]
    #[TestDox('a chosen preset applies its colors even when posted colors differ')]
    public function preset_colors_win_over_posted_colors(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'theme' => 'asia',
            // Stale / mismatched colors, as if JS never filled the inputs.
            'primary_color' => '#000000',
            'secondary_color' => '#000000',
            'background_color' => '#000000',
            'text_color' => '#000000',
        ])->assertRedirect(route('admin.branding.edit'));

        $asia = BrandingService::THEMES['asia'];
        $this->assertSame('asia', Setting::get('branding.theme'));
        $this->assertSame($asia['primary_color'], Setting::get('branding.primary_color'));
        $this->assertSame($asia['background_color'], Setting::get('branding.background_color'));
    }

    #[Test]
    #[TestDox('selecting a preset applies its hard-coded logo color')]
    public function preset_applies_its_logo_color(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'theme' => 'asia',
            'primary_color' => '#e5ba22', 'secondary_color' => '#37abc8',
            // Posted logo_color should be ignored in favor of the theme's.
            'logo_color' => '#000000',
        ])->assertRedirect();

        $this->assertSame(BrandingService::THEMES['asia']['logo_color'], Setting::get('branding.logo_color'));
    }

    #[Test]
    #[TestDox('the custom theme stores the hand-picked logo color')]
    public function custom_theme_stores_logo_color(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'theme' => 'custom',
            'primary_color' => '#123456', 'secondary_color' => '#abcdef',
            'logo_color' => '#abc123',
        ])->assertRedirect();

        $this->assertSame('#abc123', Setting::get('branding.logo_color'));
    }

    #[Test]
    #[TestDox('omitting the theme records a custom scheme')]
    public function omitting_theme_records_custom(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'primary_color' => '#123456',
            'secondary_color' => '#abcdef',
        ])->assertRedirect();

        $this->assertSame('custom', Setting::get('branding.theme'));
    }

    #[Test]
    #[TestDox('an unknown theme is rejected')]
    public function unknown_theme_rejected(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'theme' => 'mars',
            'primary_color' => '#123456',
            'secondary_color' => '#abcdef',
        ])->assertSessionHasErrors('theme');
    }

    #[Test]
    #[TestDox('removing the uploaded logo reveals the theme logo')]
    public function removing_uploaded_logo_reveals_theme_logo(): void
    {
        $admin = $this->createAdmin();

        // Upload a custom logo against the Asia theme.
        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'theme' => 'asia',
            'primary_color' => '#000000', 'secondary_color' => '#000000',
            'logo' => UploadedFile::fake()->image('mine.png'),
        ]);
        $this->assertNotNull(Setting::get('branding.logo_data'));

        // Removing it falls back to the theme's bundled logo.
        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'theme' => 'asia',
            'primary_color' => '#000000', 'secondary_color' => '#000000',
            'remove_logo' => '1',
        ])->assertRedirect();

        $this->assertNull(Setting::get('branding.logo_data'));

        $branding = app(BrandingService::class);
        $this->assertFalse($branding->hasUploadedLogo());
        $this->assertSame($branding->themeLogoUrl('asia'), $branding->logoUrl());
    }

    #[Test]
    #[TestDox('a malformed color is rejected')]
    public function malformed_color_rejected(): void
    {
        $this->actingAs($this->createAdmin())->put(route('admin.branding.update'), [
            'primary_color' => 'red',
            'secondary_color' => '#abcdef',
        ])->assertSessionHasErrors('primary_color');
    }

    #[Test]
    #[TestDox('a non-admin cannot change branding')]
    public function non_admin_cannot_change_branding(): void
    {
        $this->actingAs($this->createUser())->get(route('admin.branding.edit'))->assertForbidden();
    }
}
