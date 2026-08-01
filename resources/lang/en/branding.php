<?php

return [
    'settings' => 'Settings',
    'settings_saved' => 'Settings saved.',

    'site_name' => 'Site / event name',
    'theme' => 'Theme',
    'custom_theme' => 'Custom',
    'logo' => 'Logo',
    'save' => 'Save',

    'theme_heading' => 'Theme',
    'theme_intro' => 'A theme sets the colors and a matching logo. Pick one, or choose "Custom" to hand-pick colors. You can always upload your own logo to override the theme\'s.',
    'apply_theme' => 'Apply the :theme theme',
    'primary_color' => 'Primary color',
    'secondary_color' => 'Secondary / accent color',
    'background_color' => 'Background color',
    'text_color' => 'Text color',
    'logo_color' => 'Top bar / button text color',
    'logo_color_hint' => 'Preset themes take this from their logo; set it to match your own logo.',
    'logo_uploaded' => 'Showing your uploaded logo (overrides the theme logo).',
    'logo_theme' => 'Showing the theme\'s logo.',
    'logo_none' => 'No logo for this theme.',
    'logo_selected' => 'Showing your selected logo (overrides the theme logo).',
    'remove_logo' => 'Remove uploaded logo and use the theme\'s logo',
    'upload_logo' => 'Upload a custom logo (optional — overrides the theme logo)',
    'url_label' => 'Logo & title link',
    'url_hint' => 'Where the logo and site title link to; it opens in a new tab. Leave blank to use the selected theme\'s conference site.',

    // Shown by the client-side PDF export (see views/partials/pdf-scripts) when
    // generation fails — usually the CDN or the font route being unreachable.
    'pdf_failed' => 'The PDF could not be generated. Check your network connection and try again.',
];
