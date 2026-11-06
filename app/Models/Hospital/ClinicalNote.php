<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nota SOAP: el contenido guarda las cuatro secciones en JSON. Firmada, no cambia; se corrige con una versión nueva. */
class ClinicalNote extends HospitalModel
{
    protected $primaryKey = 'clinical_note_id';

    protected $fillable = ['medical_record_id', 'care_episode_id', 'author_user_ref_id', 'note_type', 'content', 'status', 'signed_at', 'version_no', 'correction_reason', 'supersedes_id'];

    protected $casts = ['signed_at' => 'datetime', 'version_no' => 'integer'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'author_user_ref_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(CareEpisode::class, 'care_episode_id');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class, 'clinical_note_id');
    }

    /** @return array{s: string, o: string, a: string, p: string} */
    public function sections(): array
    {
        $data = json_decode($this->content, true);

        return is_array($data) ? array_merge(['s' => '', 'o' => '', 'a' => '', 'p' => ''], $data) : ['s' => '', 'o' => '', 'a' => $this->content, 'p' => ''];
    }
}
