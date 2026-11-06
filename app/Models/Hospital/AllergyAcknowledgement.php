<?php

namespace App\Models\Hospital;

/** Módulo alergias clínicas: reconocimiento de una alerta ALTA o CRÍTICA por un usuario, ligado a su huella (RN-09). */
class AllergyAcknowledgement extends HospitalModel
{
    protected $table = 'allergy_alert_acknowledgements';

    protected $primaryKey = 'acknowledgement_id';

    public $timestamps = false;

    protected $fillable = ['local_patient_id', 'acknowledged_by_user_ref_id', 'alert_level', 'alert_fingerprint', 'acknowledged_at'];

    protected $casts = ['acknowledged_at' => 'datetime'];
}
