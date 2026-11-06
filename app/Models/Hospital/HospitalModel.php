<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Modelos de la base clínica del hospital de la petición (ver HospitalDatabase). */
abstract class HospitalModel extends Model
{
    use HasUuids;

    protected $connection = 'hospital';

    public $incrementing = false;

    protected $keyType = 'string';
}
