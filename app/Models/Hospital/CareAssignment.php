<?php

namespace App\Models\Hospital;

/** Médico asignado a un episodio: la base solo deja escribir notas al médico asignado. */
class CareAssignment extends HospitalModel
{
    protected $primaryKey = 'care_assignment_id';

    protected $fillable = ['care_episode_id', 'doctor_profile_id', 'specialty_id', 'assigned_by_user_ref_id', 'scope', 'reason', 'source', 'status', 'valid_from', 'valid_to'];

    protected $casts = ['scope' => 'array', 'valid_from' => 'datetime', 'valid_to' => 'datetime'];
}
