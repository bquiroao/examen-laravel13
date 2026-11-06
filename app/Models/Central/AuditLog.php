<?php

namespace App\Models\Central;

use App\Casts\JsonObject;
use Illuminate\Database\Eloquent\Model;

/** Bitácora de solo inserción: un trigger rechaza UPDATE, DELETE y TRUNCATE. */
class AuditLog extends Model
{
    protected $connection = 'central';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['detalle' => JsonObject::class, 'ocurrido_en' => 'datetime', 'registrado_en' => 'datetime'];
}
