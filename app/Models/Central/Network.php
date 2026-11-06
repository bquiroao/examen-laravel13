<?php

namespace App\Models\Central;

use App\Casts\JsonObject;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Network extends CentralModel
{
    protected $primaryKey = 'network_id';

    protected $fillable = ['name', 'status', 'timezone', 'metadata'];

    protected $casts = ['metadata' => JsonObject::class];

    public function hospitals(): HasMany
    {
        return $this->hasMany(Hospital::class, 'network_id');
    }
}
