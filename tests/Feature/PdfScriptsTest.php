<?php

namespace ConferenceTools\Branding\Tests\Feature;

use ConferenceTools\Branding\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * The shared client-side PDF machinery: the font route serving the bundled
 * DejaVu faces, and the pdf-scripts partial the consuming packages include
 * on every page with a PDF export.
 */
#[TestDox('Pdf Scripts')]
class PdfScriptsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('bundledFonts')]
    #[TestDox('the bundled pdf fonts are served with long lived caching')]
    public function test_the_bundled_pdf_fonts_are_served_with_long_lived_caching(string $font): void
    {
        $response = $this->get(route('admin.branding.fonts', ['font' => $font]))
            ->assertOk()
            ->assertHeader('Content-Type', 'font/ttf');

        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
    }

    /** The font files bundled with the package. */
    public static function bundledFonts(): array
    {
        return [
            'regular' => ['DejaVuSans.ttf'],
            'bold' => ['DejaVuSans-Bold.ttf'],
            'italic' => ['DejaVuSans-Oblique.ttf'],
            'bold italic' => ['DejaVuSans-BoldOblique.ttf'],
        ];
    }

    #[TestDox('fonts outside the bundled set are not served')]
    public function test_fonts_outside_the_bundled_set_are_not_served(): void
    {
        // The route pattern only admits simple .ttf names, and the controller
        // whitelists the four bundled faces — nothing else on the filesystem
        // is reachable.
        $this->get(route('admin.branding.fonts', ['font' => 'NotBundled.ttf']))->assertNotFound();
    }

    #[TestDox('the pdf scripts partial bakes the pinned libraries branding and font routes')]
    public function test_the_pdf_scripts_partial_bakes_the_pinned_libraries_branding_and_font_routes(): void
    {
        $html = view('branding::partials.pdf-scripts')->render();

        // The pinned jsPDF + AutoTable loads from unpkg (the CDN jsPDF's own
        // README documents), the conferencePdf helper, every font face's
        // route, and the localized failure message.
        $this->assertMatchesRegularExpression('~<script src="https://unpkg\.com/jspdf@[\d.]+/dist/jspdf\.umd\.min\.js" integrity="sha384-[A-Za-z0-9+/=]+" crossorigin="anonymous"~', $html);
        $this->assertMatchesRegularExpression('~<script src="https://unpkg\.com/jspdf-autotable@[\d.]+/dist/jspdf\.plugin\.autotable\.min\.js" integrity="sha384-[A-Za-z0-9+/=]+" crossorigin="anonymous"~', $html);
        $this->assertStringContainsString('window.conferencePdf', $html);
        foreach (array_column(self::bundledFonts(), 0) as $font) {
            // Route URLs are emitted through @json, which escapes the slashes.
            $this->assertStringContainsString('fonts\/'.$font, $html);
        }
        $this->assertStringContainsString(__('branding::branding.pdf_failed'), $html);
    }
}
