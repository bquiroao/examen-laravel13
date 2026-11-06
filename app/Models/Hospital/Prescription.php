<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends HospitalModel
{
    protected $primaryKey = 'prescription_id';

    protected $fillable = ['local_patient_id', 'care_episode_id', 'prescriber_user_ref_id', 'medication_code', 'medication_name', 'dose', 'route',
        'frequency', 'duration_days', 'instructions', 'status', 'allergy_override_reason', 'cancel_reason', 'cancelled_at', 'issued_at'];

    protected $casts = ['issued_at' => 'datetime', 'cancelled_at' => 'datetime', 'duration_days' => 'integer'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(LocalPatient::class, 'local_patient_id');
    }

    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'prescriber_user_ref_id');
    }
}
