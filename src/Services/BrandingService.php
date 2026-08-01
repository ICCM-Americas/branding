<?php

namespace ConferenceTools\Branding\Services;

use ConferenceTools\Branding\Contracts\BrandingProvider;
use ConferenceTools\Branding\Models\Setting;

/**
 * Reads/writes branding configuration (name, logo, colors) used on both
 * public and authenticated pages and to build the web app manifest.
 *
 * This is the host-facing implementation of the package's BrandingProvider
 * seam: the consuming conference-tools packages (bof-scheduler, registration)
 * render their own views through the contract, and the host binds this service
 * to it so those views — and the host's own layout — pick up real branding.
 *
 * The logo is stored in the database (base64-encoded in the branding_settings
 * table) rather than on disk, so deployments don't depend on a writable storage
 * path or PHP upload tuning beyond the small `max:2048` limit.
 */
class BrandingService implements BrandingProvider
{
    public const DEFAULTS = [
        'site_name' => null, // falls back to config('app.name')
        'primary_color' => '#2563eb',
        'secondary_color' => '#7c3aed',
        'background_color' => '#f8fafc',
        'text_color' => '#0f172a',
    ];

    /**
     * Ready-made color schemes derived from the original ICCM "Birds of a
     * Feather" regional stylesheets. Across those regions only two colors
     * actually differed — the page background and the logo backdrop — so each
     * theme maps that distinctive regional accent onto `primary` (the header /
     * brand surface) and the regional page tint onto `background`, while keeping
     * the shared BoF sky-blue as the `secondary` accent.
     *
     * Africa and Australia used a plain white logo backdrop, so they fall back
     * to the shared BoF lilac for a usable header color and share one palette.
     *
     * Each theme also ships the original regional logo (bundled under
     * resources/branding/themes); it is used unless an admin uploads their own.
     * `logo_color` is the logo's dominant brand color (extracted once from that
     * image and hard-coded here); it is used for top-bar and primary-button text.
     *
     * `url` is the matching regional conference site (from the "Conferences"
     * menu on iccm.org, matched by region name); it is where the logo and site
     * title link by default unless an admin sets their own.
     *
     * Keyed by a stable slug; `label` is what admins see in the picker.
     *
     * @var array<string, array{label: string, url: string, primary_color: string, secondary_color: string, background_color: string, text_color: string, logo: string, logo_color: string}>
     */
    public const THEMES = [
        'africa' => [
            'label' => 'Africa',
            'url' => 'https://iccm.africa',
            'primary_color' => '#37abc8',
            'secondary_color' => '#5a2ca0',
            'background_color' => '#ffffff',
            'text_color' => '#0f172a',
            'logo' => 'africa.png',
            'logo_color' => '#7e652c',
        ],
        'americas' => [
            'label' => 'Americas',
            'url' => 'https://americas.iccm.org',
            'primary_color' => '#37abc8',
            'secondary_color' => '#363839',
            'background_color' => '#e6e6e6',
            'text_color' => '#0f172a',
            'logo' => 'americas.png',
            'logo_color' => '#fafafa',
        ],
        'asia' => [
            'label' => 'Asia',
            'url' => 'https://asia.iccm.org',
            'primary_color' => '#e5ba22',
            'secondary_color' => '#37abc8',
            'background_color' => '#f5e5ab',
            'text_color' => '#0f172a',
            'logo' => 'asia.png',
            'logo_color' => '#232929',
        ],
        'australia' => [
            'label' => 'Australia',
            'url' => 'https://iccm-australia.org',
            'primary_color' => '#37abc8',
            'secondary_color' => '#5a2ca0',
            'background_color' => '#ffffff',
            'text_color' => '#0f172a',
            'logo' => 'australia.png',
            'logo_color' => '#000033',
        ],
        'europe' => [
            'label' => 'Europe',
            'url' => 'https://iccm-europe.org',
            'primary_color' => '#37abc8',
            'secondary_color' => '#2c0c57',
            'background_color' => '#e6e6e6',
            'text_color' => '#0f172a',
            'logo' => 'europe.png',
            'logo_color' => '#ffffff',
        ],
    ];

    /** Filesystem directory holding the bundled per-theme logos. */
    private function themeLogoDir(): string
    {
        return dirname(__DIR__, 2).'/resources/branding/themes';
    }

    /** The configured site name, or the theme default. */
    public function siteName(): string
    {
        return Setting::get('branding.site_name') ?: config('app.name', 'Meeting Sessions');
    }

    /** The compact site name used by the web app manifest. */
    public function shortName(): string
    {
        $name = $this->siteName();

        return mb_strlen($name) > 12 ? mb_substr($name, 0, 12) : $name;
    }

    /** Whether an admin has uploaded a custom logo (overriding the theme's). */
    public function hasUploadedLogo(): bool
    {
        return (bool) Setting::get('branding.logo_data');
    }

    /** Whether any logo will be shown — an uploaded one or the theme's bundled one. */
    public function hasLogo(): bool
    {
        return $this->hasUploadedLogo() || $this->themeLogoPath($this->theme()) !== null;
    }

    /** The stored logo's MIME type, or null without a logo. */
    public function logoMime(): ?string
    {
        if ($this->hasUploadedLogo()) {
            return Setting::get('branding.logo_mime', 'image/png');
        }

        // The bundled theme logos are all PNGs.
        return $this->themeLogoPath($this->theme()) !== null ? 'image/png' : null;
    }

    /**
     * The logo as a `data:` URI, suitable for an <img src>, the apple-touch-icon
     * link, the web app manifest, and the client-side PDF exports (all of which
     * accept data URIs).
     *
     * An uploaded logo takes precedence; otherwise the current theme's bundled
     * logo is used. Null when neither is available (e.g. the "Custom" theme with
     * no upload).
     */
    public function logoUrl(): ?string
    {
        if ($data = Setting::get('branding.logo_data')) {
            return 'data:'.$this->logoMime().';base64,'.$data;
        }

        return $this->themeLogoUrl($this->theme());
    }

    /** Absolute path to a theme's bundled logo file, or null if it has none. */
    public function themeLogoPath(string $slug): ?string
    {
        $logo = self::THEMES[$slug]['logo'] ?? null;

        if (! $logo) {
            return null;
        }

        $path = $this->themeLogoDir().'/'.$logo;

        return is_file($path) ? $path : null;
    }

    /** A theme's bundled logo as a `data:` URI, or null if it has none. */
    public function themeLogoUrl(string $slug): ?string
    {
        $path = $this->themeLogoPath($slug);

        return $path ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }

    /** The effective color for a branding key. */
    public function color(string $key): string
    {
        return Setting::get('branding.'.$key.'_color', self::DEFAULTS[$key.'_color'] ?? '#000000');
    }

    /**
     * Where the logo and site title link to (opened in a new tab).
     *
     * An admin-set URL takes precedence — much like an uploaded logo overrides
     * the theme's; otherwise the current theme's regional conference site is
     * used, falling back to the application home page when neither is set.
     */
    public function url(): string
    {
        return Setting::get('branding.url') ?: $this->themeUrl($this->theme()) ?? url('/');
    }

    /** A theme's matching regional conference URL, or null for a slug with none. */
    public function themeUrl(string $slug): ?string
    {
        return self::THEMES[$slug]['url'] ?? null;
    }

    /**
     * A legible foreground color (near-black or white) to place on top of the
     * given hex background, chosen by perceived brightness (the YIQ formula).
     * Keeps the header and solid buttons readable whatever brand color an
     * admin (or a preset theme) picks — e.g. the Asia gold theme gets dark text
     * while the Europe purple theme gets white.
     */
    public function contrastColor(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#ffffff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;

        return $brightness >= 128 ? '#0f172a' : '#ffffff';
    }

    /**
     * Foreground color for text/icons sitting on the given branding surface
     * ('primary' or 'secondary').
     */
    public function onColor(string $key): string
    {
        return $this->contrastColor($this->color($key));
    }

    /**
     * The color used for text on the top bar and primary buttons. It is meant
     * to match the logo: preset themes carry a hard-coded `logo_color` extracted
     * from their logo, and the "Custom" theme exposes it as an editable field so
     * an admin can match their own uploaded logo.
     *
     * Falls back to an automatically-contrasting color when nothing is set
     * (e.g. a custom theme that never configured it).
     */
    public function logoColor(): string
    {
        return Setting::get('branding.logo_color') ?: $this->onColor('primary');
    }

    /**
     * A theme's brand color (the color extracted from its logo, hard-coded in
     * {@see THEMES}), used for top-bar and primary-button text. Null for a slug
     * with no theme.
     */
    public function themeLogoColor(string $slug): ?string
    {
        return self::THEMES[$slug]['logo_color'] ?? null;
    }

    /**
     * The slug of the currently selected preset theme, or 'custom' when the
     * colors have been hand-picked (or never set to a preset).
     */
    public function theme(): string
    {
        $theme = Setting::get('branding.theme', 'custom');

        return array_key_exists($theme, self::THEMES) ? $theme : 'custom';
    }

    /**
     * @return array<string, array{label: string, primary_color: string, secondary_color: string, background_color: string, text_color: string}>
     */
    public function themes(): array
    {
        return self::THEMES;
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return [
            'site_name' => $this->siteName(),
            'logo_url' => $this->logoUrl(),
            'primary_color' => $this->color('primary'),
            'secondary_color' => $this->color('secondary'),
            'background_color' => $this->color('background'),
            'text_color' => $this->color('text'),
        ];
    }
}
