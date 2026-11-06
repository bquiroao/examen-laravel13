<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Model;

/** Plazo de acuse por prioridad (RN-04), configurable de 5 a 60 minutos (RN-11). */
class CriticalAlertPolicy extends Model
{
    protected $connection = 'hospital';

    protected $table = 'critical_alert_policies';

    protected $primaryKey = 'priority';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['priority', 'ack_minutes', 'updated_by_user_ref_id', 'updated_at'];

    protected function casts(): array
    {
        return ['ack_minutes' => 'integer', 'updated_at' => 'immutable_datetime'];
    }
}
