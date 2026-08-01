{{--
    Shared client-side PDF machinery for every conference-tools export: the
    jsPDF + AutoTable libraries from unpkg (the CDN jsPDF's own README
    documents), pinned to reviewed versions, plus a small window.conferencePdf
    helper baked with this host's branding. Reports embed their data as JSON
    and build the PDF in the admin's browser — the server ships no PDF
    renderer at all, so there is nothing to install on shared hosting and no
    (L)GPL PDF library in the dependency tree.

    Exports render in DejaVu Sans (served by the package's font route) because
    the fonts built into jsPDF cover only Latin-1, and attendee names don't.

    Include once per page, from whichever view renders the export button:

        @include('branding::partials.pdf-scripts')

    The page then defines its generator and wires the button to
    conferencePdf.run(generator). Everything below is intentionally
    framework-free and inline: the consuming packages have no JS build step,
    and copy-style deployments never run one.
--}}
@once
<script src="https://unpkg.com/jspdf@4.2.1/dist/jspdf.umd.min.js" integrity="sha384-qovJwSBbRDPP5cEjCp8S0UP66wrvnjaa60XMOGzTNanrThcrGfXfnZkvgY8N1KT3" crossorigin="anonymous" defer></script>
<script src="https://unpkg.com/jspdf-autotable@5.0.8/dist/jspdf.plugin.autotable.min.js" integrity="sha384-5jk55M0XWoAw7LyhlXJe19ErOr3doBAPzxw9vahPFbvolqWa2yDk4fhHa2zuYeOa" crossorigin="anonymous" defer></script>
<script nonce="{{ $cspNonce ?? '' }}">
window.conferencePdf = (() => {
    'use strict';

    const branding = {
        logo: @json($branding->logoUrl()),
        site: @json($branding->siteName()),
        primary: @json($branding->color('primary')),
        secondary: @json($branding->color('secondary')),
        text: @json($branding->color('text')),
    };
    const failureMessage = @json(__('branding::branding.pdf_failed'));
    const fontUrls = {
        normal: @json(route(config('branding.route_name_prefix').'fonts', ['font' => 'DejaVuSans.ttf'])),
        bold: @json(route(config('branding.route_name_prefix').'fonts', ['font' => 'DejaVuSans-Bold.ttf'])),
        italic: @json(route(config('branding.route_name_prefix').'fonts', ['font' => 'DejaVuSans-Oblique.ttf'])),
        bolditalic: @json(route(config('branding.route_name_prefix').'fonts', ['font' => 'DejaVuSans-BoldOblique.ttf'])),
    };

    // The 1.5cm page margin every flowing report shares.
    const PAGE_MARGIN = 15;

    // The deferred library <script>s above execute after this inline one, so
    // anything touching window.jspdf first waits for the window load event.
    const librariesReady = () => document.readyState === 'complete'
        ? Promise.resolve()
        : new Promise((resolve) => window.addEventListener('load', resolve, { once: true }));

    let fontsPromise = null;
    const loadFonts = () => fontsPromise ??= Promise.all(
        Object.entries(fontUrls).map(async ([style, url]) => {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`Font fetch failed: ${url} (${response.status})`);
            }
            const bytes = new Uint8Array(await response.arrayBuffer());
            let binary = '';
            for (let i = 0; i < bytes.length; i += 0x8000) {
                binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
            }
            return [style, btoa(binary)];
        }),
    );

    // A jsPDF document in mm with the full DejaVu Sans family registered and
    // selected, so every generator gets Unicode text out of the box.
    async function createDoc(options) {
        const doc = new window.jspdf.jsPDF({ unit: 'mm', ...options });
        for (const [style, data] of await loadFonts()) {
            const file = `DejaVuSans-${style}.ttf`;
            doc.addFileToVFS(file, data);
            doc.addFont(file, 'DejaVuSans', style);
        }
        doc.setFont('DejaVuSans', 'normal');
        doc.setTextColor(branding.text);
        return doc;
    }

    // Rasterizes any browser-renderable image (PNG, JPEG, SVG, data: URLs) to
    // a PNG data URL with its display size in px, since jsPDF cannot embed
    // SVG and needs the aspect ratio to place anything. Resolves null when
    // there is nothing usable — generators then simply skip the image.
    function imageData(url) {
        return new Promise((resolve) => {
            if (!url) {
                return resolve(null);
            }
            const image = new Image();
            image.onload = () => {
                const width = image.naturalWidth || 512;
                const height = image.naturalHeight || 256;
                const canvas = document.createElement('canvas');
                // 3x the display size keeps logos crisp at print resolution.
                canvas.width = width * 3;
                canvas.height = height * 3;
                canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                resolve({ data: canvas.toDataURL('image/png'), width, height });
            };
            image.onerror = () => resolve(null);
            image.src = url;
        });
    }

    // The standard report header every export shares: logo, optional
    // site line, title, and a rule in the primary color. Returns the y where
    // the page's content starts. Draw it on each page that needs one.
    async function drawHeader(doc, { title, titleSizePt = 20, site = false } = {}) {
        let y = PAGE_MARGIN;

        const logo = await imageData(branding.logo);
        if (logo) {
            const height = Math.min(12, (logo.height * 25.4) / 96);
            doc.addImage(logo.data, 'PNG', PAGE_MARGIN, y, (height * logo.width) / logo.height, height);
            y += height + 2;
        }

        if (site) {
            doc.setFontSize(11);
            doc.setTextColor(branding.secondary);
            doc.text(branding.site, PAGE_MARGIN, y, { baseline: 'top' });
            y += 6;
        }

        doc.setFontSize(titleSizePt);
        doc.setFont('DejaVuSans', 'bold');
        doc.setTextColor(branding.primary);
        doc.text(title, PAGE_MARGIN, y, { baseline: 'top' });
        // The title's own height (pt -> mm) plus the old header's .4cm pad.
        y += titleSizePt * 0.3528 + 4;

        doc.setDrawColor(branding.primary);
        doc.setLineWidth(0.8);
        doc.line(PAGE_MARGIN, y, doc.internal.pageSize.getWidth() - PAGE_MARGIN, y);

        doc.setFont('DejaVuSans', 'normal');
        doc.setTextColor(branding.text);

        return y + 6;
    }

    function timestamp() {
        const now = new Date();
        const pad = (part) => String(part).padStart(2, '0');

        return now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate())
            + '-' + pad(now.getHours()) + pad(now.getMinutes()) + pad(now.getSeconds());
    }

    // Downloads the document under the same name scheme the server-side
    // exports used: {report}-YYYYmmdd-HHiiss.pdf.
    function save(doc, basename) {
        doc.save(`${basename}-${timestamp()}.pdf`);
    }

    // Wraps a generator for direct use as a click handler: waits out the
    // deferred library loads, and turns any failure into a user-visible
    // message instead of a silent dead button.
    async function run(generator) {
        try {
            await librariesReady();
            if (!window.jspdf) {
                throw new Error('The jsPDF library did not load.');
            }
            await generator();
        } catch (error) {
            console.error(error);
            alert(failureMessage);
        }
    }

    return { branding, PAGE_MARGIN, createDoc, imageData, drawHeader, save, run };
})();
</script>
@endonce
