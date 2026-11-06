<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bed extends HospitalModel
{
    protected $primaryKey = 'bed_id';

    protected $fillable = ['ward_id', 'code', 'type', 'status', 'status_reason', 'status_changed_at'];

    protected $casts = ['status_changed_at' => 'datetime'];

    public const TYPES = ['standard' => 'General', 'uci' => 'UCI', 'aislamiento' => 'Aislamiento', 'pediatrica' => 'Pediátrica'];

    /** Módulo catálogo de salas y camas: «ocupada» no está aquí, porque la gestionan admisión, traslados y altas. */
    public const TRANSITIONS = [
        'available' => ['maintenance', 'inactive'],
        'maintenance' => ['available', 'inactive'],
        'inactive' => ['available'],
        'occupied' => [],
    ];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    /** La estancia abierta es la fuente de verdad de «ocupada» (beds.status puede quedar desfasado: defecto AU-05). */
    public function openStay(): HasOne
    {
        return $this->hasOne(BedStay::class, 'bed_id')->whereNull('ended_at');
    }
}
