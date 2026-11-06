<?php

namespace App\Models\Hospital;

class Diagnosis extends HospitalModel
{
    protected $primaryKey = 'diagnosis_id';

    protected $fillable = ['care_episode_id', 'clinical_note_id', 'author_user_ref_id', 'code', 'description', 'type', 'status', 'onset_date', 'resolved_at'];

    protected $casts = ['onset_date' => 'date:Y-m-d', 'resolved_at' => 'date:Y-m-d'];
}
