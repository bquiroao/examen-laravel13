<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorSpecialty extends HospitalModel
{
    protected $primaryKey = 'doctor_specialty_id';

    protected $fillable = ['doctor_profile_id', 'specialty_id', 'is_primary', 'valid_from', 'valid_to', 'status'];

    protected $casts = ['is_primary' => 'boolean', 'valid_from' => 'date:Y-m-d', 'valid_to' => 'date:Y-m-d'];

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class, 'specialty_id');
    }

    /** Vigente en una fecha: activa y dentro de [valid_from, valid_to). */
    public function scopeValidOn(Builder $query, string $date): Builder
    {
        return $query->where('status', 'active')->where('valid_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $date));
    }
}
