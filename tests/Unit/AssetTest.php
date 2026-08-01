<?php

namespace ConferenceTools\Branding\Tests\Unit;

use ConferenceTools\Branding\Support\Asset;
use ConferenceTools\Branding\Tests\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

/** Unit tests for the published-asset URL helper. */
#[TestDox('Asset')]
class AssetTest extends TestCase
{
    #[TestDox('an existing file gets a modification-time cache buster')]
    public function test_an_existing_file_gets_a_modification_time_cache_buster(): void
    {
        $path = 'vendor/branding/css/asset-test.css';
        $file = public_path($path);

        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, ':root { --x: 1; }');

        try {
            $this->assertSame(asset($path).'?v='.filemtime($file), Asset::url($path));
        } finally {
            unlink($file);
        }
    }

    #[TestDox('a missing file falls back to the plain asset URL')]
    public function test_a_missing_file_falls_back_to_the_plain_asset_url(): void
    {
        // A host that has not run vendor:publish yet must still render, just
        // unstyled — never fail with a filemtime error.
        $path = 'vendor/branding/css/never-published.css';

        $this->assertSame(asset($path), Asset::url($path));
    }

    #[TestDox('a directory is treated as missing rather than fingerprinted')]
    public function test_a_directory_is_treated_as_missing_rather_than_fingerprinted(): void
    {
        $this->assertSame(asset('vendor'), Asset::url('vendor'));
    }
}
