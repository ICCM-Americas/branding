# ConferenceTools Branding

Shared branding for the conference-tools Laravel packages. It provides:

- **The `ConferenceTools\Branding\Contracts\BrandingProvider` contract** — the
  small seam (`siteName()`, `color()`, `logoUrl()`) that the
  `conference-tools/bof-scheduler` and `conference-tools/registration` packages
  render their own views, PDFs and emails through.
- **A real implementation** (`Services\BrandingService`) bound to that contract,
  so those packages — and the host's own layout — show real branding with no
  wiring on the host's part.
- **The branding management UI** — an admin screen for the site/event name, a
  color theme (with bundled regional logos), a custom logo upload, the logo/title
  link and the PWA/manifest colors. Values are stored in a `branding_settings`
  key/value table.
- **The shared stylesheets** — the `iccm-*` design system and the cross-package
  layout utilities, published to `public/vendor/branding/css`. See
  [Shared styling](#shared-styling) below.

## Relationship to the other conference-tools packages

`conference-tools/bof-scheduler` and `conference-tools/registration` **depend on
this package at runtime** — they consume `BrandingProvider` and they render
markup styled by this package's stylesheets. That dependency is deliberately
**not** declared in their `composer.json`; instead, this package is installed in
the host application **alongside** whichever of those packages you use. Install
it the same way (see below).

If this package is absent, each consuming package falls back to its own neutral
`Support\DefaultBranding` so its views still render — but the branding admin
screen and shared, host-configured branding only exist when this package is
installed.

### The cross-boundary rule

Reaching across a package boundary is normally forbidden in this family of
packages. There is exactly one exception, and these are its terms:

1. **A conference-tools package may couple to `conference-tools/branding`, and
   to nothing else.** Never to the host application, never to a sibling feature
   package. Branding is the single place shared resources live.
2. **That coupling is still never a hard `require`.** It stays in `require-dev`
   in each consuming package's `composer.json`. A hard require would let
   Composer resolve two different branding versions into one application —
   two `BrandingProvider` contracts, two sets of `iccm-*` rules — which is
   exactly the failure this rule exists to prevent.
3. **In exchange, the host application MUST require
   `conference-tools/branding`.** Declaring that requirement here is what makes
   everything in this package — the contract, the stylesheets, the color themes
   — legitimately available to the feature packages, and it guarantees exactly
   one copy of it.

This is a documented convention, not a resolver-enforced constraint, and that
trade-off is deliberate: it is what lets each feature package stay independently
installable while shared resources live in exactly one place. A host that skips
step 3 gets unstyled package screens and neutral fallback branding.

## Shared styling

Two stylesheets, published to `public/vendor/branding/css`:

| File | Contents |
| --- | --- |
| `iccm.css` | The design system: page chrome (`header.iccm-app`, `nav.iccm-app`), components (`.iccm-card`, `.iccm-btn`, `.iccm-pill`, `.iccm-flash`, …), form/table defaults, and the Bootstrap re-skin that maps `.btn-primary`, `.card` and `.page-link` onto the brand colors. |
| `iccm-utilities.css` | Layout utilities shared across the packages: the `.iccm-row*` flex-row idiom, the `.iccm-field-*` width scale, `.iccm-stat-value`, `.iccm-status-line`, `.iccm-drag-item`, `.iccm-modal-overlay`, `.no-print`. |

Both are written against CSS custom properties (`--color-primary`,
`--color-secondary`, `--color-bg`, `--color-text`, `--color-on-primary`,
`--color-on-secondary`). The host layout defines those in a small inline
`:root` block, because their values come from the branding database and so
cannot live in a static file. Everything else about the design system is static
and belongs in these files.

The **host layout links them** (`<link rel="stylesheet">`); feature packages just
emit the class names. That keeps the packages free of any code dependency on
this one — a class name is not an import.

## Installation

The service provider is auto-discovered.

**For local development** against a checkout, use a path repository:

```bash
composer config repositories.branding path packages/conference-tools/branding
composer require conference-tools/branding:*
```

### Publish what you need

```bash
php artisan vendor:publish --tag=branding-config      # config/branding.php
php artisan vendor:publish --tag=branding-migrations  # database/migrations/*
php artisan vendor:publish --tag=branding-views       # resources/views/vendor/branding
php artisan vendor:publish --tag=branding-lang        # lang/vendor/branding
php artisan vendor:publish --tag=branding-assets      # public/vendor/branding/css
```

Migrations load automatically; publish only to customize them.

**`branding-assets` is not optional** — without it the stylesheets never reach
the web root and every screen renders unstyled. It is also tagged
`laravel-assets`, which Laravel's stock `post-update-cmd` composer script
already republishes with `--force`, so in practice a host only needs to run it
once by hand on first install and after any deploy that skips composer.

### Define the admin gate

The branding screen is guarded by `config('branding.admin_middleware')`, which by
default references a host-defined `manage-branding` gate. The package never
decides who an admin is:

```php
// In a host service provider:
Gate::define('manage-branding', fn ($user) => $user->isAdmin());
```

## Configuration (`config/branding.php`)

| Key | Purpose |
| --- | --- |
| `route_prefix` | URL prefix for the branding screen (default `admin/branding`). |
| `route_name_prefix` | Route-name prefix (default `admin.branding.`), so the host admin nav can link to `route('admin.branding.edit')`. |
| `admin_middleware` | Middleware guarding the screen (default `['web', 'auth', 'can:manage-branding']`). |
| `admin_gate` | Name of the host gate the middleware references. |
| `layout` | Layout the screen extends (default `layouts.app`, the host chrome; falls back to the package's neutral `branding::layouts.app`). |

## The contract

```php
interface BrandingProvider
{
    public function siteName(): string;
    public function color(string $key): string;   // primary|secondary|background|text
    public function logoUrl(): ?string;
}
```

`Services\BrandingService` implements this and adds the management-side helpers
(themes, contrast colors, logo storage, the web app manifest inputs).

## License

MIT.
