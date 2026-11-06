<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class UserCredential extends Model
{
    protected $connection = 'central';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'password_hash', 'password_changed_at'];

    protected $hidden = ['password_hash'];

    protected $casts = ['password_changed_at' => 'datetime'];
}
