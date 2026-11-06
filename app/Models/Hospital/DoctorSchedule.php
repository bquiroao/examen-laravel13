<?php

namespace App\Models\Hospital;

/** Horario semanal de atención: día (1 = lunes … 7 = domingo) y rango de horas en bloques de 30 minutos. */
class DoctorSchedule extends HospitalModel
{
    protected $primaryKey = 'doctor_schedule_id';

    protected $fillable = ['doctor_profile_id', 'weekday', 'starts_at', 'ends_at'];

    protected $casts = ['weekday' => 'integer'];
}
