<?php

namespace App\Providers;

use App\Listeners\RegistrarCierreSesion;
use App\Listeners\RegistrarInicioSesion;
use App\Models\AgentePrompt;
use App\Models\AgenteToolEstado;
use App\Models\BaseConocimientoDocumento;
use App\Models\BitacoraActividad;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\CatalogoFiscal\CatalogoActivacion;
use App\Models\CatalogoFiscal\CatalogoCasilla;
use App\Models\CatalogoFiscal\CatalogoDocumento;
use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Models\CatalogoFiscal\CatalogoLinea;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Models\DeterminacionFiscal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\NivelRiesgoManual;
use App\Models\ParametroFiscal;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Observers\AuditoriaObserver;
use App\Observers\CatalogoCambioObserver;
use App\Observers\WhatsappMensajeObserver;
use App\Policies\AgentePromptPolicy;
use App\Policies\AgenteToolPolicy;
use App\Policies\BaseConocimientoPolicy;
use App\Policies\BitacoraPolicy;
use App\Policies\CatalogoPolicy;
use App\Policies\ClientePolicy;
use App\Policies\WhatsappMensajePolicy;
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

        // Twilio es el único proveedor de WhatsApp; el resto del sistema habla
        // contra la interfaz WhatsappChannel (y las pruebas la reemplazan).
        $this->app->bind(WhatsappChannel::class, TwilioChannel::class);
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
        Gate::policy(AgenteToolEstado::class, AgenteToolPolicy::class);
        Gate::policy(BaseConocimientoDocumento::class, BaseConocimientoPolicy::class);

        // Bitácora general de la plataforma (ver App\Observers\AuditoriaObserver
        // para por qué nunca registra valores, solo nombres de atributo).
        User::observe(AuditoriaObserver::class);
        CampoCatalogo::observe(AuditoriaObserver::class);
        CampoCliente::observe(AuditoriaObserver::class);
        Documento::observe(AuditoriaObserver::class);
        FormaCliente::observe(AuditoriaObserver::class);
        DeterminacionFiscal::observe(AuditoriaObserver::class);
        NivelRiesgoManual::observe(AuditoriaObserver::class);

        // Catálogo fiscal por año (F1): las piezas principales también van a
        // la bitácora general, y todas guardan su historial con el valor
        // anterior y el nuevo (ver CatalogoCambioObserver).
        foreach ([CatalogoPregunta::class, CatalogoDocumento::class, CatalogoFormulario::class, CatalogoRegla::class] as $pieza) {
            $pieza::observe(AuditoriaObserver::class);
        }

        foreach ([CatalogoPregunta::class, CatalogoDocumento::class, CatalogoCasilla::class, CatalogoFormulario::class, CatalogoLinea::class, CatalogoActivacion::class, CatalogoRegla::class, ParametroFiscal::class] as $pieza) {
            $pieza::observe(CatalogoCambioObserver::class);
        }

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
