<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property UserRole $role
 * @property int|null $preparer_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, FormaCliente> $formasCliente
 * @property-read Collection<int, CampoCliente> $camposCliente
 */
#[Fillable(['name', 'email', 'phone', 'password', 'role', 'preparer_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
        ];
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
}
