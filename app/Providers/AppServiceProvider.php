<?php

namespace App\Providers;

use App\Listeners\RegistrarCierreSesion;
use App\Listeners\RegistrarInicioSesion;
use App\Models\AgentePrompt;
use App\Models\BitacoraActividad;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\DeterminacionFiscal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\NivelRiesgoManual;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Observers\AuditoriaObserver;
use App\Observers\WhatsappMensajeObserver;
use App\Policies\AgentePromptPolicy;
use App\Policies\BitacoraPolicy;
use App\Policies\CatalogoPolicy;
use App\Policies\ClientePolicy;
use App\Policies\WhatsappMensajePolicy;
use App\Services\Whatsapp\Meta\MetaChannel;
use App\Services\Whatsapp\Twilio\TwilioChannel;
use App\Services\Whatsapp\WhatsappChannel;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Twilio\Rest\Client as TwilioClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Enlazado explícito (no dejar que el contenedor lo auto-resuelva por
        // reflexión): el constructor de TwilioClient acepta todos sus
        // argumentos como nullable y cae a leer variables de entorno crudas
        // (getenv()) si no se le pasan — con esto, siempre usa
        // config('services.twilio.*'), igual que el resto de la app.
        $this->app->singleton(TwilioClient::class, fn () => new TwilioClient(
            (string) config('services.twilio.account_sid'),
            (string) config('services.twilio.auth_token'),
        ));

        // "El switch" entre proveedores de WhatsApp (ver
        // App\Services\Whatsapp\WhatsappChannel) — cambiar WHATSAPP_PROVIDER
        // en .env es lo único que hace falta para pasar de Twilio a Meta o
        // viceversa; el resto del sistema nunca conoce cuál está activo.
        $this->app->bind(WhatsappChannel::class, fn ($app) => match (config('services.whatsapp.provider')) {
            'meta' => $app->make(MetaChannel::class),
            default => $app->make(TwilioChannel::class),
        });
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
        Gate::policy(WhatsappMensaje::class, WhatsappMensajePolicy::class);
        Gate::policy(AgentePrompt::class, AgentePromptPolicy::class);

        // Bitácora general de la plataforma (ver App\Observers\AuditoriaObserver
        // para por qué nunca registra valores, solo nombres de atributo).
        User::observe(AuditoriaObserver::class);
        CampoCatalogo::observe(AuditoriaObserver::class);
        CampoCliente::observe(AuditoriaObserver::class);
        Documento::observe(AuditoriaObserver::class);
        FormaCliente::observe(AuditoriaObserver::class);
        DeterminacionFiscal::observe(AuditoriaObserver::class);
        NivelRiesgoManual::observe(AuditoriaObserver::class);

        // Realtime: cada WhatsappMensaje guardado se transmite por Reverb en
        // el canal privado de su teléfono (ver WhatsappMensajeRecibido).
        WhatsappMensaje::observe(WhatsappMensajeObserver::class);

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
