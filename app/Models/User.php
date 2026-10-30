<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Entrevista\Entrevista;
use App\Services\UsuarioRetencionService;
use App\Support\TelefonoWhatsapp;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $contrasena_definida_en
 * @property UserRole $role
 * @property int|null $preparer_id
 * @property bool $es_prueba
 * @property bool $entrevista_catalogo
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, FormaCliente> $formasCliente
 * @property-read Collection<int, CampoCliente> $camposCliente
 * @property-read Collection<int, DeterminacionFiscal> $determinacionesFiscales
 */
#[Fillable(['name', 'email', 'phone', 'password', 'role', 'preparer_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, Prunable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'contrasena_definida_en' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'es_prueba' => 'boolean',
            'entrevista_catalogo' => 'boolean',
        ];
    }

    /**
     * Los clientes nuevos toman la bandera de la entrevista nueva de
     * `tax.entrevista_catalogo_por_defecto` (falso hasta 2.10).
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->role === UserRole::Client && ! array_key_exists('entrevista_catalogo', $user->getAttributes())) {
                $user->entrevista_catalogo = (bool) config('tax.entrevista_catalogo_por_defecto');
            }
        });
    }

    /**
     * Único lugar que lee la bandera de la entrevista sobre el catálogo
     * fiscal (F2.2). Se borra en 2.11, cuando todos usen el flujo nuevo.
     */
    public function usaEntrevistaCatalogo(): bool
    {
        return $this->role === UserRole::Client && $this->entrevista_catalogo;
    }

    /**
     * Clientes archivados hace más de 3 años (§6107, Form 8867). Preparado
     * pero sin programar en el scheduler hasta que se confirme el plazo (L12).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()
            ->where('role', UserRole::Client)
            ->where('deleted_at', '<=', now()->subYears(3));
    }

    protected function pruning(): void
    {
        app(UsuarioRetencionService::class)->borrarArchivosDe($this);
    }

    /**
     * Normaliza a "+dígitos" (siempre con "+", sin espacios/guiones/paréntesis)
     * cualquier valor que se le asigne a `phone` — mismo formato en el que
     * Twilio entrega `WhatsappMensaje.telefono` (ver
     * App\Support\TelefonoWhatsapp, fuente única de esta normalización,
     * compartida con TwilioChannel::normalizarEntrante). Sin esto, un teléfono tecleado a mano en /usuarios
     * con un formato distinto (sin "+", con guiones, etc.) no calza con el
     * `where('telefono', ...)` exacto de ClienteController::conversacionWhatsapp,
     * y esa conversación se ve vacía pese a existir en whatsapp_mensajes.
     *
     * @return Attribute<?string, ?string>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TelefonoWhatsapp::normalizar($value),
        );
    }

    /**
     * The clients managed by this user, when acting as a preparer.
     *
     * @return HasMany<User, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(User::class, 'preparer_id');
    }

    /**
     * The preparer managing this user's tax documents, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preparer_id');
    }

    /**
     * @return HasMany<Entrevista, $this>
     */
    public function entrevistas(): HasMany
    {
        return $this->hasMany(Entrevista::class);
    }

    /**
     * @return HasMany<FormaCliente, $this>
     */
    public function formasCliente(): HasMany
    {
        return $this->hasMany(FormaCliente::class);
    }

    /**
     * @return HasMany<CampoCliente, $this>
     */
    public function camposCliente(): HasMany
    {
        return $this->hasMany(CampoCliente::class);
    }

    /**
     * @return HasMany<DeterminacionFiscal, $this>
     */
    public function determinacionesFiscales(): HasMany
    {
        return $this->hasMany(DeterminacionFiscal::class);
    }

    /**
     * F0.1: su rol exige MFA (`security.mfa_roles`) y todavía no tiene ni la
     * verificación en dos pasos confirmada ni una passkey.
     */
    public function debeConfigurarMfa(): bool
    {
        if (! \in_array($this->role?->value, config('security.mfa_roles', []), true)) {
            return false;
        }

        return ! $this->hasEnabledTwoFactorAuthentication() && ! $this->passkeys()->exists();
    }
}
