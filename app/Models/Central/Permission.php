<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $connection = 'central';

    protected $primaryKey = 'permission_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['permission_code', 'module', 'description'];
}
