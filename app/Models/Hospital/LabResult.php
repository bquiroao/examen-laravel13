<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabResult extends HospitalModel
{
    protected $primaryKey = 'lab_result_id';

    protected $fillable = ['lab_order_item_id', 'value_numeric', 'unit', 'flag', 'status', 'entered_by_user_ref_id', 'validated_by_user_ref_id',
        'validated_at', 'review_note', 'version_no', 'supersedes_id'];

    protected $casts = ['value_numeric' => 'float', 'validated_at' => 'datetime', 'version_no' => 'integer'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(LabOrderItem::class, 'lab_order_item_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'entered_by_user_ref_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'validated_by_user_ref_id');
    }
}
