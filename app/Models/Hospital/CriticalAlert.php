<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Alerta crítica (módulo alertas-criticas-notificaciones-internas). Guarda el código del paciente, nunca su nombre. */
class CriticalAlert extends HospitalModel
{
    protected $primaryKey = 'critical_alert_id';

    protected $fillable = ['local_patient_id', 'patient_code', 'care_episode_id', 'source', 'source_id', 'priority', 'title', 'description', 'status',
        'level', 'deadline_at', 'escalation_exhausted', 'acknowledged_by_user_ref_id', 'acknowledged_at', 'acknowledged_level', 'closed_by_user_ref_id',
        'closed_at', 'close_note'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'acknowledged_level' => 'integer', 'escalation_exhausted' => 'boolean', 'deadline_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(CriticalAlertEvent::class, 'critical_alert_id')->orderBy('occurred_at')->orderBy('critical_alert_event_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(CriticalAlertNotification::class, 'critical_alert_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'acknowledged_by_user_ref_id', 'local_user_ref_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'closed_by_user_ref_id', 'local_user_ref_id');
    }

    /** Sigue esperando acuse: se puede acusar y se escala si vence el plazo. */
    public function awaitingAcknowledgement(): bool
    {
        return in_array($this->status, ['ENVIADA', 'ESCALADA'], true);
    }
}
