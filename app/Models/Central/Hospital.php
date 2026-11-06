<?php

namespace App\Models\Central;

use App\Casts\JsonObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hospital extends CentralModel
{
    protected $primaryKey = 'hospital_id';

    protected $fillable = ['network_id', 'code', 'name', 'status', 'timezone', 'clinical_endpoint', 'schema_version', 'metadata'];

    protected $casts = ['metadata' => JsonObject::class];

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HospitalMembership::class, 'hospital_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
