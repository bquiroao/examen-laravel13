<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Orden de ingreso pendiente de cama (módulo admisión hospitalaria y asignación de cama). Al asignarle una cama se
 * crea el internamiento y la orden queda «asignada»; de ahí sale el tiempo de espera de cama.
 */
class AdmissionOrder extends HospitalModel
{
    protected $primaryKey = 'admission_order_id';

    protected $fillable = ['local_patient_id', 'ordered_by_user_ref_id', 'bed_type', 'isolation', 'source', 'priority', 'reason', 'status', 'ordered_at',
        'care_episode_id', 'assigned_at', 'assigned_by_user_ref_id', 'cancelled_at', 'cancelled_by_user_ref_id', 'cancel_reason'];

    protected function casts(): array
    {
        return ['isolation' => 'boolean', 'ordered_at' => 'immutable_datetime', 'assigned_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(LocalPatient::class, 'local_patient_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'ordered_by_user_ref_id', 'local_user_ref_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'assigned_by_user_ref_id', 'local_user_ref_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'cancelled_by_user_ref_id', 'local_user_ref_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(CareEpisode::class, 'care_episode_id');
    }
}
