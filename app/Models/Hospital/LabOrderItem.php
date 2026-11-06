<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LabOrderItem extends HospitalModel
{
    protected $primaryKey = 'lab_order_item_id';

    protected $fillable = ['lab_order_id', 'test_code', 'test_name', 'specimen_type', 'unit', 'ref_low', 'ref_high', 'critical_low', 'critical_high',
        'status', 'specimen_code', 'received_at', 'received_by_user_ref_id', 'rejection_reason'];

    protected $casts = ['received_at' => 'datetime', 'ref_low' => 'float', 'ref_high' => 'float', 'critical_low' => 'float', 'critical_high' => 'float'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class, 'lab_order_item_id')->orderBy('version_no');
    }

    /** El resultado vigente: capturado (pendiente de validar) o validado. */
    public function currentResult(): HasOne
    {
        return $this->hasOne(LabResult::class, 'lab_order_item_id')->whereIn('status', ['captured', 'validated']);
    }
}
