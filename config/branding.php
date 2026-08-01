<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | The branding management screen mounts under this URL prefix and route-name
    | prefix. They default to the host application's admin area
    | ("/admin/branding", named "admin.branding.*") so the host's admin navigation
    | can link to route('admin.branding.edit') without configuration. Change them
    | to move the screen elsewhere.
    |
    */

    'route_prefix' => env('BRANDING_ROUTE_PREFIX', 'admin/branding'),
    'route_name_prefix' => env('BRANDING_ROUTE_NAME_PREFIX', 'admin.branding.'),

    /*
    |--------------------------------------------------------------------------
    | Middleware & authorization
    |--------------------------------------------------------------------------
    |
    | The branding screen is administrative. "admin_middleware" guards it via the
    | host-defined gate below; the package never decides who an admin is — the
    | host application defines Gate::define('manage-branding', ...).
    |
    */

    'admin_middleware' => ['web', 'auth', 'can:manage-branding'],
    'admin_gate' => env('BRANDING_ADMIN_GATE', 'manage-branding'),

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | The branding screen extends this layout. It defaults to the host layout
    | ("layouts.app") so the screen renders inside the host application's chrome.
    | The package ships a neutral fallback ("branding::layouts.app") used when no
    | host layout exists (e.g. the package's own isolated test suite).
    |
    */

    'layout' => env('BRANDING_LAYOUT', 'layouts.app'),

];
