<?php

namespace App\Http\Middleware;

use App\Models\Central\CentralUser;
use App\Services\Access\PermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Token válido, usuario activo y emitido para ESTE hospital (claim hid). Corrige el defecto del módulo
 * usuarios/tenants que aceptaba un token de otro tenant, y el E-06 (la clase JwtAuth chocaba con el alias JWTAuth).
 */
class AuthenticateWithJwt
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            /** @var CentralUser|null $user */
            $user = JWTAuth::authenticate();
        } catch (JWTException) {
            return response()->json(['message' => 'Sesión inválida o expirada.'], 401);
        }

        $hospital = $request->attributes->get('hospital');

        if ($user === null || ! $user->isActive()) {
            return response()->json(['message' => 'Sesión inválida o expirada.'], 401);
        }

        if ($payload->get('hid') !== $hospital->hospital_id) {
            return response()->json(['message' => 'El hospital indicado no coincide con la sesión.'], 403);
        }

        // Un rol revocado corta el acceso de inmediato, sin esperar a que venza el token.
        if ($this->permissions->rolesFor($user->user_id, $hospital->hospital_id) === []) {
            return response()->json(['message' => 'Ya no tiene acceso a este hospital.'], 403);
        }

        auth()->setUser($user);

        return $next($request);
    }
}
