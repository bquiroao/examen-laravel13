<?php

namespace App\Http\Middleware;

use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Módulo RBAC, protección de rutas: `permiso:roles.asignar`. Un acceso denegado queda en la bitácora. */
class RequirePermission
{
    public function __construct(private readonly PermissionResolver $permissions, private readonly AuditTrail $audit) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $hospital = $request->attributes->get('hospital');

        // «permiso:a|b» basta con uno de los dos.
        $allowed = collect(explode('|', $permission))->contains(fn (string $p) => $this->permissions->allows($user->user_id, $hospital->hospital_id, $p));

        if (! $allowed) {
            $this->audit->record('rbac', 'ACCESO_DENEGADO', 'denegado', $hospital->hospital_id, $user->user_id,
                $request->method().' '.$request->path(), ['permiso' => $permission], $request->ip());

            return response()->json(['message' => 'No tiene permiso para esta acción.', 'code' => 'PERMISO_NO_ASIGNADO'], 403);
        }

        return $next($request);
    }
}
