<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A quién se avisó de una alerta, en qué nivel, cuántos intentos de entrega llevó (RN-08) y si ya la vio. */
class CriticalAlertNotification extends HospitalModel
{
    protected $primaryKey = 'notification_id';

    public $timestamps = false;

    protected $fillable = ['critical_alert_id', 'recipient_user_ref_id', 'level', 'attempts', 'delivered', 'last_attempt_at', 'read_at'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'attempts' => 'integer', 'delivered' => 'boolean', 'last_attempt_at' => 'immutable_datetime', 'read_at' => 'immutable_datetime'];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(CriticalAlert::class, 'critical_alert_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'recipient_user_ref_id', 'local_user_ref_id');
    }
}
