<?php

namespace App\Models\Central;

use App\Casts\JsonObject;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tymon\JWTAuth\Contracts\JWTSubject;

class CentralUser extends CentralModel implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;

    protected $primaryKey = 'user_id';

    protected $fillable = ['subject', 'email', 'display_name', 'status', 'last_authenticated_at', 'metadata'];

    protected $casts = ['metadata' => JsonObject::class, 'last_authenticated_at' => 'datetime'];

    /** Sin "recordarme": la sesión es un JWT corto y central_users no tiene esa columna. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function credential(): HasOne
    {
        return $this->hasOne(UserCredential::class, 'user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HospitalMembership::class, 'user_id');
    }

    public function getAuthPassword(): string
    {
        return (string) $this->credential?->password_hash;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /** El hospital (hid) lo agrega AuthService al emitir el token. */
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
