<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedStay extends HospitalModel
{
    protected $primaryKey = 'bed_stay_id';

    protected $fillable = ['care_episode_id', 'bed_id', 'assigned_by_user_ref_id', 'started_at', 'ended_at', 'reason'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(CareEpisode::class, 'care_episode_id');
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'bed_id');
    }
}
