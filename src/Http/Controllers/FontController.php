<?php

namespace ConferenceTools\Branding\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the package-bundled DejaVu Sans faces (see resources/fonts/LICENSE)
 * to the client-side PDF generators, which embed them so exports keep full
 * Unicode coverage — the fonts jsPDF ships with are Latin-1 only. Served from
 * the package itself so installing the package is all a host has to do: no
 * publish step, which the copy-and-symlink deployments never run.
 */
class FontController extends Controller
{
    /** The servable faces; anything else is a 404, keeping this off the filesystem. */
    private const FONTS = [
        'DejaVuSans.ttf',
        'DejaVuSans-Bold.ttf',
        'DejaVuSans-Oblique.ttf',
        'DejaVuSans-BoldOblique.ttf',
    ];

    /** Serve a bundled DejaVu font file for the client-side PDF exports. */
    public function __invoke(string $font): BinaryFileResponse
    {
        abort_unless(in_array($font, self::FONTS, true), 404);

        return response()->file(__DIR__.'/../../../resources/fonts/'.$font, [
            'Content-Type' => 'font/ttf',
            // The faces only change with a package upgrade; let browsers keep
            // them so repeated exports fetch each face once.
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
