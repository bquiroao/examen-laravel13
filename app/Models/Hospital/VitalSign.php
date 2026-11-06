<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSign extends HospitalModel
{
    protected $primaryKey = 'vital_sign_id';

    protected $fillable = [
        'medical_record_id', 'care_episode_id', 'recorded_by_user_ref_id', 'temperature', 'heart_rate', 'respiratory_rate', 'systolic_bp',
        'diastolic_bp', 'oxygen_saturation', 'weight', 'height', 'glucose', 'measured_at', 'recorded_at', 'status', 'validated_at', 'version_no',
        'correction_reason', 'supersedes_id',
    ];

    protected $casts = ['measured_at' => 'datetime', 'recorded_at' => 'datetime', 'validated_at' => 'datetime', 'temperature' => 'float',
        'oxygen_saturation' => 'float', 'weight' => 'float', 'height' => 'float', 'glucose' => 'float'];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'recorded_by_user_ref_id');
    }
}
