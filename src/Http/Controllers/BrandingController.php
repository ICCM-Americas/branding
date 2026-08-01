<?php

namespace ConferenceTools\Branding\Http\Controllers;

use ConferenceTools\Branding\Models\Setting;
use ConferenceTools\Branding\Services\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Branding management: site name, logo, and color scheme. It feeds the public
 * and authenticated pages and the web app manifest of any host application that
 * installs this package.
 */
class BrandingController extends Controller
{
    private const HEX = ['regex:/^#[0-9a-fA-F]{6}$/'];

    /** The branding management form. */
    public function edit(BrandingService $branding): View
    {
        return view('branding::branding', ['branding' => $branding]);
    }

    /** Save the site name, URL, theme colors, and logo. */
    public function update(Request $request): RedirectResponse
    {
        $themes = array_keys(BrandingService::THEMES);

        $validated = $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'url', 'max:255'],
            'theme' => ['nullable', Rule::in([...$themes, 'custom'])],
            'primary_color' => ['required', ...self::HEX],
            'secondary_color' => ['required', ...self::HEX],
            'background_color' => ['nullable', ...self::HEX],
            'text_color' => ['nullable', ...self::HEX],
            'logo_color' => ['nullable', ...self::HEX],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        // A chosen preset's colors always win over the posted inputs (which may
        // be stale if the client-side preview never ran).
        $theme = $validated['theme'] ?? 'custom';
        $preset = BrandingService::THEMES[$theme] ?? [];

        Setting::put('branding.site_name', $validated['site_name'] ?? null);
        // An empty URL is stored as null so the logo/title fall back to the
        // selected theme's conference site (see BrandingService::url()).
        Setting::put('branding.url', $validated['url'] ?? null);
        Setting::put('branding.theme', $theme);
        Setting::put('branding.primary_color', $preset['primary_color'] ?? $validated['primary_color']);
        Setting::put('branding.secondary_color', $preset['secondary_color'] ?? $validated['secondary_color']);
        Setting::put('branding.background_color', $preset['background_color'] ?? $validated['background_color'] ?? null);
        Setting::put('branding.text_color', $preset['text_color'] ?? $validated['text_color'] ?? null);
        Setting::put('branding.logo_color', $preset['logo_color'] ?? $validated['logo_color'] ?? null);

        if ($request->boolean('remove_logo')) {
            Setting::put('branding.logo_data', null);
            Setting::put('branding.logo_mime', null);
        } elseif ($request->hasFile('logo')) {
            $file = $request->file('logo');
            Setting::put('branding.logo_data', base64_encode((string) file_get_contents($file->getRealPath())));
            Setting::put('branding.logo_mime', $file->getMimeType());
        }

        return redirect()->route($this->routeName('edit'))->with('status', __('branding::branding.settings_saved'));
    }
}
