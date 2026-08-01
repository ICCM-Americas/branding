<?php

namespace ConferenceTools\Branding\Contracts;

use ConferenceTools\Branding\Services\BrandingService;

/**
 * The branding seam shared across the conference-tools packages. It is the
 * canonical contract: the conference-tools/bof-scheduler and
 * conference-tools/registration packages consume this interface for their own
 * views (fallback layouts, PDFs, emails), and the host application binds a real
 * implementation — the {@see BrandingService}
 * shipped in this package — so those views pick up the host's configured
 * branding.
 *
 * Branding *management* (themes, logo upload, color config, the admin screen)
 * lives in this package too; the consuming packages only ever read through this
 * contract and never depend on the management side.
 */
interface BrandingProvider
{
    /** The site/event display name. */
    public function siteName(): string;

    /** A hex color for the given key (e.g. primary|secondary|background|text). */
    public function color(string $key): string;

    /** A logo as a data/URL string, or null when none is configured. */
    public function logoUrl(): ?string;

    /** The conference/event website URL (where the site title and logo link). */
    public function url(): string;
}
