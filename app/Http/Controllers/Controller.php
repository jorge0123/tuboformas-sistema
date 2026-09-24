<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /** Autoriza si el usuario tiene al menos uno de los permisos. */
    protected function autorizarAlguno(array $permisos): void
    {
        abort_unless(auth()->user()?->canAny($permisos), 403);
    }
}
