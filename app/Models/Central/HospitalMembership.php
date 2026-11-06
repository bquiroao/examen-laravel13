<?php

namespace App\Models\Central;

use App\Casts\JsonObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalMembership extends CentralModel
{
    protected $primaryKey = 'membership_id';

    protected $fillable = ['user_id', 'hospital_id', 'role_code', 'status', 'valid_from', 'valid_to', 'metadata'];

    protected $casts = ['metadata' => JsonObject::class, 'valid_from' => 'datetime', 'valid_to' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'user_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_code');
    }

    /** Módulo RBAC, ReglaMembresiaVigente: activa y dentro de su vigencia. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('valid_from', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()));
    }
}
