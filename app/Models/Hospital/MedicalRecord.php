<?php

namespace App\Models\Hospital;

class MedicalRecord extends HospitalModel
{
    protected $primaryKey = 'medical_record_id';

    protected $fillable = ['local_patient_id', 'record_number', 'opened_at', 'status', 'closed_at'];

    protected $casts = ['opened_at' => 'datetime', 'closed_at' => 'datetime'];
}
