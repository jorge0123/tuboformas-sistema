<?php

namespace App\Providers;

use App\Support\Permisos;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // El super administrador pasa cualquier verificación de permisos.
        Gate::before(fn ($user) => $user->hasRole(Permisos::SUPER_ADMIN) ? true : null);

        Carbon::setLocale('es');
        Paginator::defaultView('components.paginacion');

        Blade::directive('num', fn ($e) => "<?php echo e(\\App\\Support\\Formato::numero($e)); ?>");
        Blade::directive('dinero', fn ($e) => "<?php echo e(\\App\\Support\\Formato::dinero($e)); ?>");
        Blade::directive('fecha', fn ($e) => "<?php echo e(\\App\\Support\\Formato::fecha($e)); ?>");
    }
}
