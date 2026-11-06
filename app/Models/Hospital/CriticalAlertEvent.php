<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un paso del historial de una alerta (RN-10): qué pasó, quién y cuándo. Solo se agrega; no se edita ni se borra. */
class CriticalAlertEvent extends HospitalModel
{
    protected $primaryKey = 'critical_alert_event_id';

    public $timestamps = false;

    protected $fillable = ['critical_alert_id', 'event', 'level', 'actor', 'actor_user_ref_id', 'detail', 'occurred_at'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'detail' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(CriticalAlert::class, 'critical_alert_id');
    }
}
