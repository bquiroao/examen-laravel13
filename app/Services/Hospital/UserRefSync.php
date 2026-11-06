<?php

namespace App\Services\Hospital;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Central\HospitalMembership;
use App\Models\Hospital\LocalUserRef;

/**
 * La base del hospital no guarda usuarios ni credenciales: guarda una copia mínima (local_user_refs) de quien
 * actúa en él, para firmar sus registros clínicos. Se actualiza cada vez que el usuario actúa.
 */
class UserRefSync
{
    public function forUser(CentralUser $user, Hospital $hospital, ?string $preferRole = null): LocalUserRef
    {
        $membership = HospitalMembership::query()->current()->where('user_id', $user->user_id)->where('hospital_id', $hospital->hospital_id)
            ->orderByRaw('role_code = ? DESC', [$preferRole ?? ''])->orderBy('role_code')->first()
            ?? throw new DomainRuleException('SIN_ACCESO_AL_HOSPITAL', 'El usuario no tiene un rol vigente en este hospital.', 403);

        return LocalUserRef::query()->updateOrCreate(['central_user_id' => $user->user_id], [
            'membership_id' => $membership->membership_id,
            'display_name' => $user->display_name,
            'role_code' => $membership->role_code,
            'status' => 'active',
            'valid_to' => $membership->valid_to,
            'synced_at' => now(),
        ]);
    }
}
