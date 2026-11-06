<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una versión de un medicamento del catálogo (módulo catalogo-medicamentos). El código MED-NNNNNN es el mismo en
 * todas sus versiones; la vigencia es [valid_from, valid_to). Una versión no se edita: solo se cierra.
 */
class Medication extends HospitalModel
{
    protected $primaryKey = 'medication_id';

    public $timestamps = false;

    protected $fillable = ['code', 'version', 'name', 'active_ingredient', 'strength_value', 'strength_unit', 'dosage_form', 'route', 'presentation',
        'sanitary_registration', 'therapeutic_group', 'allergy_groups', 'legacy_code', 'equivalence_key', 'search_text', 'valid_from', 'valid_to',
        'close_reason', 'created_by_user_ref_id', 'closed_by_user_ref_id', 'created_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'allergy_groups' => 'array', 'valid_from' => 'immutable_date', 'valid_to' => 'immutable_date', 'created_at' => 'immutable_datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'created_by_user_ref_id', 'local_user_ref_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'closed_by_user_ref_id', 'local_user_ref_id');
    }

    /** Vigente en la fecha AAAA-MM-DD: [valid_from, valid_to). */
    public function scopeValidOn(Builder $query, string $date): Builder
    {
        return $query->where('valid_from', '<=', $date)->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $date));
    }
}
