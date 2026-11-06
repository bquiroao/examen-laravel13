<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una versión de una prueba del catálogo de laboratorio (módulo catalogo-pruebas-laboratorio), con sus requisitos de
 * muestra. La vigencia es [valid_from, valid_to]: valid_to es el último día vigente. Una versión no se edita.
 */
class LabTest extends HospitalModel
{
    protected $primaryKey = 'lab_test_id';

    public $timestamps = false;

    protected $fillable = ['code', 'version', 'name', 'section', 'turnaround_hours', 'specimen_type', 'container', 'min_volume_ml', 'fasting_hours',
        'preparation_instructions', 'storage', 'stability_hours', 'unit', 'ref_low', 'ref_high', 'critical_low', 'critical_high', 'search_text',
        'initial_load', 'valid_from', 'valid_to', 'created_by_user_ref_id', 'closed_by_user_ref_id', 'created_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'turnaround_hours' => 'integer', 'fasting_hours' => 'integer', 'stability_hours' => 'integer',
            'initial_load' => 'boolean', 'valid_from' => 'immutable_date', 'valid_to' => 'immutable_date', 'created_at' => 'immutable_datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'created_by_user_ref_id', 'local_user_ref_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'closed_by_user_ref_id', 'local_user_ref_id');
    }

    /** Vigente en la fecha AAAA-MM-DD: [valid_from, valid_to], con valid_to incluido (RN-06). */
    public function scopeValidOn(Builder $query, string $date): Builder
    {
        return $query->where('valid_from', '<=', $date)->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date));
    }
}
