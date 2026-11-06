<?php

namespace App\Services\Access;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Central\HospitalMembership;
use App\Models\Central\Role;
use App\Services\Audit\AuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Módulo RBAC: reglas de asignación y revocación de roles; cada cambio queda en la bitácora. */
class RoleAssignmentService
{
    public const MOTIVO_MINIMO = 10;

    public function __construct(private readonly PermissionResolver $permissions, private readonly AuditTrail $audit) {}

    public function assign(CentralUser $admin, Hospital $hospital, string $userId, string $roleCode,
        ?CarbonImmutable $validFrom = null, ?CarbonImmutable $validTo = null, ?string $ip = null): HospitalMembership
    {
        $validFrom ??= CarbonImmutable::now();
        if ($admin->user_id === $userId) {
            throw new DomainRuleException('AUTOASIGNACION', 'No puede asignarse roles a sí mismo.');
        }
        $role = Role::query()->find($roleCode);
        if ($role === null) {
            throw new DomainRuleException('ROL_INVALIDO', 'El rol no existe en el catálogo.');
        }
        if ($role->scope === 'network' && ! $this->permissions->allows($admin->user_id, $hospital->hospital_id, 'roles.configurar')) {
            throw new DomainRuleException('ROL_FUERA_DE_ALCANCE', 'Solo un administrador de red puede asignar este rol.', 403);
        }
        $target = CentralUser::query()->find($userId);
        if ($target === null) {
            throw new DomainRuleException('USUARIO_NO_ENCONTRADO', 'El usuario no existe.', 404);
        }
        if (! $target->isActive()) {
            throw new DomainRuleException('USUARIO_INACTIVO', 'El usuario está inactivo.');
        }
        if ($validTo !== null && $validTo <= $validFrom) {
            throw new DomainRuleException('VIGENCIA_INVALIDA', 'La fecha final debe ser posterior a la inicial.');
        }

        return DB::connection('central')->transaction(function () use ($admin, $hospital, $userId, $roleCode, $validFrom, $validTo, $ip): HospitalMembership {
            $exists = HospitalMembership::query()->where('user_id', $userId)->where('hospital_id', $hospital->hospital_id)
                ->where('role_code', $roleCode)->where('status', 'active')->whereNull('valid_to')->lockForUpdate()->exists();
            if ($exists) {
                throw new DomainRuleException('ASIGNACION_DUPLICADA', 'El usuario ya tiene ese rol en este hospital.', 409);
            }
            $membership = HospitalMembership::query()->create([
                'user_id' => $userId, 'hospital_id' => $hospital->hospital_id, 'role_code' => $roleCode,
                'status' => 'active', 'valid_from' => $validFrom, 'valid_to' => $validTo, 'metadata' => ['asignado_por' => $admin->user_id],
            ]);
            $this->audit->record('rbac', 'ROL_ASIGNADO', 'exito', $hospital->hospital_id, $admin->user_id, 'usuario:'.$userId,
                ['rol' => $roleCode, 'membresia' => $membership->membership_id], $ip);
            $this->permissions->forget();

            return $membership;
        });
    }

    public function revoke(CentralUser $admin, Hospital $hospital, string $membershipId, string $motivo, ?string $ip = null): HospitalMembership
    {
        $membership = HospitalMembership::query()->current()->where('hospital_id', $hospital->hospital_id)->find($membershipId);
        if ($membership === null) {
            throw new DomainRuleException('MEMBRESIA_NO_ENCONTRADA', 'La asignación no existe o ya no está vigente.', 404);
        }
        if ($membership->user_id === $admin->user_id) {
            throw new DomainRuleException('AUTORREVOCACION', 'No puede revocar sus propios roles.');
        }
        if (Role::query()->whereKey($membership->role_code)->value('scope') === 'network'
            && ! $this->permissions->allows($admin->user_id, $hospital->hospital_id, 'roles.configurar')) {
            throw new DomainRuleException('ROL_FUERA_DE_ALCANCE', 'Solo un administrador de red puede revocar este rol.', 403);
        }
        if (mb_strlen(trim($motivo)) < self::MOTIVO_MINIMO) {
            throw new DomainRuleException('MOTIVO_INSUFICIENTE', 'El motivo debe tener al menos '.self::MOTIVO_MINIMO.' caracteres.');
        }

        return DB::connection('central')->transaction(function () use ($admin, $hospital, $membership, $motivo, $ip): HospitalMembership {
            $metadata = array_merge($membership->metadata ?? [], ['revocado_por' => $admin->user_id, 'motivo' => trim($motivo)]);
            $membership->forceFill(['status' => 'revoked', 'metadata' => $metadata])->save();
            $this->audit->record('rbac', 'ROL_REVOCADO', 'exito', $hospital->hospital_id, $admin->user_id, 'usuario:'.$membership->user_id,
                ['rol' => $membership->role_code, 'membresia' => $membership->membership_id, 'motivo' => trim($motivo)], $ip);
            $this->permissions->forget();

            return $membership;
        });
    }
}
