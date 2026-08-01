<?php

use ConferenceTools\Branding\Http\Controllers\BrandingController;
use ConferenceTools\Branding\Http\Controllers\FontController;
use Illuminate\Support\Facades\Route;

$namePrefix = config('branding.route_name_prefix');
$routePrefix = config('branding.route_prefix');

// The bundled PDF-export fonts (see FontController). Deliberately outside the
// admin gate: the faces are public data, and gating them would only break the
// export for a session that expires between page load and export click.
Route::get($routePrefix.'/fonts/{font}', FontController::class)
    ->where('font', '[A-Za-z-]+\.ttf')
    ->name($namePrefix.'fonts');

// The branding management screen. Gated by the host-defined "manage-branding"
// gate (see config('branding.admin_middleware')); the host application decides
// who an admin is. Defaults mount it at the host admin area ("/admin/branding",
// named "admin.branding.*").
Route::middleware(config('branding.admin_middleware'))
    ->prefix($routePrefix)
    ->group(function () use ($namePrefix) {
        Route::get('/', [BrandingController::class, 'edit'])->name($namePrefix.'edit');
        Route::put('/', [BrandingController::class, 'update'])->name($namePrefix.'update');
    });
