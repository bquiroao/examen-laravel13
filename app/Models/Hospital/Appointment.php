<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends HospitalModel
{
    protected $primaryKey = 'appointment_id';

    protected $fillable = [
        'local_patient_id', 'doctor_profile_id', 'specialty_id', 'starts_at', 'ends_at', 'status', 'reason',
        'status_reason', 'status_changed_at', 'booked_by_user_ref_id',
    ];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'status_changed_at' => 'datetime'];

    public const ACTIVE = ['scheduled', 'confirmed'];

    /** Módulo citas: transiciones permitidas. Atendida y cancelada son finales. */
    public const TRANSITIONS = [
        'scheduled' => ['confirmed', 'cancelled'],
        'confirmed' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(LocalPatient::class, 'local_patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class, 'specialty_id');
    }
}
