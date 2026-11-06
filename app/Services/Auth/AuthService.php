<?php

namespace App\Services\Auth;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tymon\JWTAuth\Facades\JWTAuth;

/** Módulo usuarios/tenants: inicio de sesión por hospital, con mensaje genérico y límite de intentos. */
class AuthService
{
    public function __construct(private readonly PermissionResolver $permissions, private readonly AuditTrail $audit) {}

    /** @return array<string, mixed> */
    public function login(Hospital $hospital, string $email, string $password, ?string $ip): array
    {
        $key = 'login:'.mb_strtolower(trim($email)).'|'.$ip;
        if (RateLimiter::tooManyAttempts($key, (int) config('nido.auth.max_login_attempts'))) {
            throw new DomainRuleException('DEMASIADOS_INTENTOS', 'Demasiados intentos. Espere unos minutos antes de volver a intentar.', 429);
        }

        $user = CentralUser::query()->with('credential')->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
        $valid = $user !== null && $user->isActive() && $user->credential !== null
            && Hash::check($password, $user->credential->password_hash)
            && $this->permissions->rolesFor($user->user_id, $hospital->hospital_id) !== [];

        if (! $valid) {
            RateLimiter::hit($key, (int) config('nido.auth.login_decay_seconds'));
            $this->audit->record('autenticacion', 'INICIO_SESION', 'denegado', $hospital->hospital_id, $user?->user_id, null, ['email' => mb_strtolower(trim($email))], $ip);
            // Mismo mensaje si el correo no existe, la contraseña falla o no tiene rol en este hospital.
            throw new DomainRuleException('CREDENCIALES_INVALIDAS', 'Correo, contraseña u hospital incorrectos.', 401);
        }

        RateLimiter::clear($key);
        $user->forceFill(['last_authenticated_at' => now()])->save();
        $this->audit->record('autenticacion', 'INICIO_SESION', 'exito', $hospital->hospital_id, $user->user_id, null, [], $ip);

        return $this->issue($user, $hospital);
    }

    /** @return array<string, mixed> */
    public function issue(CentralUser $user, Hospital $hospital): array
    {
        $token = JWTAuth::claims(['hid' => $hospital->hospital_id])->fromUser($user);

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'session' => $this->session($user, $hospital),
        ];
    }

    /** @return array<string, mixed> */
    public function session(CentralUser $user, Hospital $hospital): array
    {
        return [
            'user' => ['id' => $user->user_id, 'name' => $user->display_name, 'email' => $user->email],
            'hospital' => ['id' => $hospital->hospital_id, 'code' => $hospital->code, 'name' => $hospital->name, 'timezone' => $hospital->timezone],
            'roles' => $this->permissions->rolesFor($user->user_id, $hospital->hospital_id),
            'permissions' => $this->permissions->permissionsFor($user->user_id, $hospital->hospital_id),
        ];
    }
}
