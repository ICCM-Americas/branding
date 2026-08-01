<?php

namespace ConferenceTools\Branding\Support;

/**
 * Builds cache-busted URLs for the published conference-tools stylesheets.
 *
 * Lives here because branding is the one package the host and every other
 * conference-tools package may depend on, so a single implementation serves
 * all of their layouts.
 */
class Asset
{
    /**
     * The public URL for a published asset, fingerprinted by its modification
     * time so a redeployed stylesheet is never served from a stale cache.
     */
    public static function url(string $path): string
    {
        $file = public_path($path);
        $stamp = is_file($file) ? filemtime($file) : false;

        return $stamp === false
            ? asset($path)
            : asset($path).'?v='.$stamp;
    }
}
