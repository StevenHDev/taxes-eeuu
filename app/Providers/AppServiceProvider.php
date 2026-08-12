<?php

namespace App\Providers;

use App\Listeners\RegistrarCierreSesion;
use App\Listeners\RegistrarInicioSesion;
use App\Models\BitacoraActividad;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\DeterminacionFiscal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\NivelRiesgoManual;
use App\Models\User;
use App\Observers\AuditoriaObserver;
use App\Policies\BitacoraPolicy;
use App\Policies\CatalogoPolicy;
use App\Policies\ClientePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::policy(User::class, ClientePolicy::class);
        Gate::policy(CampoCatalogo::class, CatalogoPolicy::class);
        Gate::policy(BitacoraActividad::class, BitacoraPolicy::class);

        // Bitácora general de la plataforma (ver App\Observers\AuditoriaObserver
        // para por qué nunca registra valores, solo nombres de atributo).
        User::observe(AuditoriaObserver::class);
        CampoCatalogo::observe(AuditoriaObserver::class);
        CampoCliente::observe(AuditoriaObserver::class);
        Documento::observe(AuditoriaObserver::class);
        FormaCliente::observe(AuditoriaObserver::class);
        DeterminacionFiscal::observe(AuditoriaObserver::class);
        NivelRiesgoManual::observe(AuditoriaObserver::class);

        Event::listen(Login::class, RegistrarInicioSesion::class);
        Event::listen(Logout::class, RegistrarCierreSesion::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
