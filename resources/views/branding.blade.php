@extends($brandingLayout)

@section('title', __('branding::branding.settings'))

@php
    $hasUploadedLogo = $branding->hasUploadedLogo();
    // Per-theme logo data URIs, for the live preview and swatches.
    $themeLogos = collect($branding->themes())
        ->mapWithKeys(fn ($theme, $slug) => [$slug => $branding->themeLogoUrl($slug)]);
    // Per-theme top-bar/button text color, extracted from each logo image.
    $themeLogoColors = collect($branding->themes())
        ->mapWithKeys(fn ($theme, $slug) => [$slug => $branding->themeLogoColor($slug)]);
@endphp

@section('content')
    <h1>{{ __('branding::branding.settings') }}</h1>

    <div class="iccm-card">
        <form method="POST" action="{{ route($routeName('update')) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <label for="site_name">{{ __('branding::branding.site_name') }}</label>
            <input id="site_name" type="text" name="site_name" value="{{ old('site_name', \ConferenceTools\Branding\Models\Setting::get('branding.site_name')) }}">

            <h2 class="theme-heading">{{ __('branding::branding.theme_heading') }}</h2>
            <p class="iccm-muted mt-0">{{ __('branding::branding.theme_intro') }}</p>

            <label for="theme">{{ __('branding::branding.theme') }}</label>
            <select id="theme" name="theme">
                <option value="custom">{{ __('branding::branding.custom_theme') }}</option>
                @foreach ($branding->themes() as $slug => $theme)
                    <option value="{{ $slug }}" @selected(old('theme', $branding->theme()) === $slug)>{{ $theme['label'] }}</option>
                @endforeach
            </select>

            <div id="theme-swatches" class="theme-swatches-row">
                @foreach ($branding->themes() as $slug => $theme)
                    <button type="button" class="iccm-ghost theme-swatch theme-swatch-btn" data-theme="{{ $slug }}"
                            title="{{ __('branding::branding.apply_theme', ['theme' => $theme['label']]) }}">
                        @if ($themeLogos[$slug])
                            <img src="{{ $themeLogos[$slug] }}" alt="" class="theme-swatch-logo">
                        @endif
                        <span class="theme-swatch-colors">
                            <span data-color="{{ $theme['primary_color'] }}"></span>
                            <span data-color="{{ $theme['secondary_color'] }}"></span>
                            <span data-color="{{ $theme['background_color'] }}"></span>
                        </span>
                        {{ $theme['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="iccm-grid-2">
                <div>
                    <label for="primary_color">{{ __('branding::branding.primary_color') }}</label>
                    @php($v = old('primary_color', $branding->color('primary')))
                    <input id="primary_color" type="color" name="primary_color" value="{{ $v }}">
                </div>
                <div>
                    <label for="secondary_color">{{ __('branding::branding.secondary_color') }}</label>
                    @php($v = old('secondary_color', $branding->color('secondary')))
                    <input id="secondary_color" type="color" name="secondary_color" value="{{ $v }}">
                </div>
                <div>
                    <label for="background_color">{{ __('branding::branding.background_color') }}</label>
                    @php($v = old('background_color', $branding->color('background')))
                    <input id="background_color" type="color" name="background_color" value="{{ $v }}">
                </div>
                <div>
                    <label for="text_color">{{ __('branding::branding.text_color') }}</label>
                    @php($v = old('text_color', $branding->color('text')))
                    <input id="text_color" type="color" name="text_color" value="{{ $v }}">
                </div>
                <div>
                    <label for="logo_color">{{ __('branding::branding.logo_color') }}</label>
                    @php($v = old('logo_color', $branding->logoColor()))
                    <input id="logo_color" type="color" name="logo_color" value="{{ $v }}">
                    <p class="iccm-muted hint-tight">{{ __('branding::branding.logo_color_hint') }}</p>
                </div>
            </div>

            <label>{{ __('branding::branding.logo') }}</label>
            <div class="logo-row">
                <img id="logo-preview" src="{{ $branding->logoUrl() }}" alt="" {{ $branding->logoUrl() ? '' : 'hidden' }}>
                <span id="logo-source" class="iccm-muted">
                    {{ $hasUploadedLogo ? __('branding::branding.logo_uploaded') : __('branding::branding.logo_theme') }}
                </span>
            </div>

            @if ($hasUploadedLogo)
                <div class="iccm-checkbox-row checkbox-row-tight">
                    <input id="remove_logo" type="checkbox" name="remove_logo" value="1">
                    <label for="remove_logo" class="label-inline">{{ __('branding::branding.remove_logo') }}</label>
                </div>
            @endif

            <label for="logo" class="label-weight-normal">{{ __('branding::branding.upload_logo') }}</label>
            <input id="logo" type="file" name="logo" accept="image/*">

            <label for="url">{{ __('branding::branding.url_label') }}</label>
            <input id="url" type="url" name="url" value="{{ old('url', \ConferenceTools\Branding\Models\Setting::get('branding.url')) }}"
                   placeholder="{{ $branding->themeUrl($branding->theme()) ?? url('/') }}">
            <p class="iccm-muted hint-tight">{{ __('branding::branding.url_hint') }}</p>


            <button type="submit" class="save-btn">{{ __('branding::branding.save') }}</button>
        </form>
    </div>

    @push('head')
        <link rel="stylesheet" href="{{ \ConferenceTools\Branding\Support\Asset::url('vendor/branding/css/branding.css') }}">
        <script nonce="{{ $cspNonce ?? '' }}">
            // The form lives in <body>, but this stack renders in <head>, so wait
            // for the DOM before wiring up the live preview.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.theme-swatch-colors [data-color]').forEach(function (el) {
                    el.style.background = el.dataset.color;
                });

                const themes = @json($branding->themes());
                const themeLogos = @json($themeLogos);
                const themeLogoColors = @json($themeLogoColors);
                const hasUploadedLogo = @json($hasUploadedLogo);
                const select = document.getElementById('theme');
                const fields = ['primary', 'secondary', 'background', 'text'];
                const colorFields = ['primary', 'secondary', 'background', 'text', 'logo'];

                // Each color picker shows its own value as its background.
                function syncSwatch(input) {
                    if (input) input.style.background = input.value;
                }
                function syncAllSwatches() {
                    colorFields.forEach(function (name) {
                        syncSwatch(document.getElementById(name + '_color'));
                    });
                }
                const urlInput = document.getElementById('url');
                const homeUrl = @json(url('/'));

                // The link field is an optional override: show the selected theme's
                // conference site as the placeholder so a blank field is clearly
                // "follow the theme".
                function refreshUrlPlaceholder() {
                    const theme = themes[select.value];
                    urlInput.placeholder = (theme && theme.url) ? theme.url : homeUrl;
                }

                const logoPreview = document.getElementById('logo-preview');
                const logoSource = document.getElementById('logo-source');
                const removeLogo = document.getElementById('remove_logo');
                const logoInput = document.getElementById('logo');

                // True while the uploaded logo is still in effect (not pending removal
                // and not replaced by a fresh file selection in this session).
                function uploadedLogoActive() {
                    if (!hasUploadedLogo) return false;
                    if (removeLogo && removeLogo.checked) return false;
                    if (logoInput.files && logoInput.files.length) return false;
                    return true;
                }

                function showLogo(src, label) {
                    if (src) {
                        logoPreview.src = src;
                        logoPreview.hidden = false;
                    } else {
                        logoPreview.removeAttribute('src');
                        logoPreview.hidden = true;
                    }
                    if (label) logoSource.textContent = label;
                }

                // Reflect the currently selected theme's logo, unless an uploaded
                // logo is overriding it.
                function refreshThemeLogo() {
                    if (uploadedLogoActive()) return;
                    const src = themeLogos[select.value] || null;
                    showLogo(src, src ? @json(__('branding::branding.logo_theme')) : @json(__('branding::branding.logo_none')));
                }

                function applyTheme(slug) {
                    const theme = themes[slug];
                    if (!theme) return; // 'custom' leaves the current colors untouched
                    fields.forEach(function (name) {
                        const input = document.getElementById(name + '_color');
                        if (input) input.value = theme[name + '_color'];
                    });
                    // The top-bar/button text color comes from the theme's logo.
                    const logoColor = document.getElementById('logo_color');
                    if (logoColor && themeLogoColors[slug]) logoColor.value = themeLogoColors[slug];
                    syncAllSwatches();
                }

                select.addEventListener('change', function () {
                    applyTheme(this.value);
                    refreshThemeLogo();
                    refreshUrlPlaceholder();
                });

                document.querySelectorAll('.theme-swatch').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        select.value = this.dataset.theme;
                        applyTheme(this.dataset.theme);
                        refreshThemeLogo();
                        refreshUrlPlaceholder();
                    });
                });

                // Hand-editing any color means the scheme is no longer a named preset.
                colorFields.forEach(function (name) {
                    const input = document.getElementById(name + '_color');
                    if (input) {
                        input.addEventListener('input', function () {
                            select.value = 'custom';
                            syncSwatch(this);
                        });
                    }
                });

                // Reflect the initial values (e.g. after validation re-fills old input).
                syncAllSwatches();

                // Preview a freshly chosen file; it overrides the theme logo.
                logoInput.addEventListener('change', function () {
                    if (this.files && this.files[0]) {
                        showLogo(URL.createObjectURL(this.files[0]), @json(__('branding::branding.logo_selected')));
                    } else {
                        refreshThemeLogo();
                    }
                });

                // Ticking "remove" falls back to the theme logo immediately.
                if (removeLogo) {
                    removeLogo.addEventListener('change', refreshThemeLogo);
                }
            });
        </script>
    @endpush
@endsection
