<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabOrder extends HospitalModel
{
    protected $primaryKey = 'lab_order_id';

    protected $fillable = ['local_patient_id', 'care_episode_id', 'ordered_by_user_ref_id', 'priority', 'clinical_indication', 'status'];

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class, 'lab_order_id')->orderBy('test_name');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(LocalPatient::class, 'local_patient_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'ordered_by_user_ref_id');
    }
}
