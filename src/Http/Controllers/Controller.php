<?php

namespace ConferenceTools\Branding\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/** Base class for the package's controllers. */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /** Fully-qualified package route name, honoring the configured name prefix. */
    protected function routeName(string $name): string
    {
        return config('branding.route_name_prefix').$name;
    }
}
