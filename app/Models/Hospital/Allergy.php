<?php

namespace App\Models\Hospital;

/**
 * Alergia registrada (tabla allergies del esquema base). El registro y la alerta visual son del módulo
 * alergias-clinicas-alerta-visual-expediente; aquí solo se LEEN para verificar las prescripciones.
 */
class Allergy extends HospitalModel
{
    protected $primaryKey = 'allergy_id';

    protected $guarded = [];
}
