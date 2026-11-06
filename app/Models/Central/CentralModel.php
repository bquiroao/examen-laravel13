<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

abstract class CentralModel extends Model
{
    use HasUuids;

    protected $connection = 'central';

    public $incrementing = false;

    protected $keyType = 'string';
}
