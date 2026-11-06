<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareEpisode extends HospitalModel
{
    protected $primaryKey = 'care_episode_id';

    protected $fillable = ['local_patient_id', 'type', 'status', 'started_at', 'ended_at', 'reason', 'service_code', 'discharge_type', 'discharge_reason'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public const DISCHARGE_TYPES = ['medica' => 'Alta médica', 'voluntaria' => 'Alta voluntaria', 'referencia' => 'Referencia a otro centro', 'defuncion' => 'Defunción'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(LocalPatient::class, 'local_patient_id');
    }

    public function stays(): HasMany
    {
        return $this->hasMany(BedStay::class, 'care_episode_id')->orderBy('started_at');
    }
}
