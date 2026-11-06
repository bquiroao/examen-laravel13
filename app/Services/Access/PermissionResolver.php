<?php

namespace App\Services\Access;

use App\Models\Central\HospitalMembership;
use Illuminate\Support\Facades\DB;

/** Módulo RBAC (ReglaMembresiaVigente + ReglaRolConcedePermiso): permisos efectivos de un usuario en un hospital. */
class PermissionResolver
{
    /** @var array<string, list<string>> */
    private array $cache = [];

    /** @return list<string> */
    public function rolesFor(string $userId, string $hospitalId): array
    {
        return HospitalMembership::query()->current()
            ->where('user_id', $userId)->where('hospital_id', $hospitalId)
            ->orderBy('role_code')->pluck('role_code')->unique()->values()->all();
    }

    /** @return list<string> */
    public function permissionsFor(string $userId, string $hospitalId): array
    {
        return $this->cache[$userId.'|'.$hospitalId] ??= DB::connection('central')->table('role_permissions')
            ->whereIn('role_code', $this->rolesFor($userId, $hospitalId))
            ->orderBy('permission_code')->distinct()->pluck('permission_code')->all();
    }

    public function allows(string $userId, string $hospitalId, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($userId, $hospitalId), true);
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
