<?php

namespace App\Models\Hospital;

/** Copia local del usuario de CENTRAL que actúa en este hospital (sin credenciales). */
class LocalUserRef extends HospitalModel
{
    protected $primaryKey = 'local_user_ref_id';

    protected $fillable = ['central_user_id', 'membership_id', 'display_name', 'role_code', 'status', 'valid_to', 'synced_at'];

    protected $casts = ['valid_to' => 'datetime', 'synced_at' => 'datetime'];
}
