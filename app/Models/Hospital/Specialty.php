<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Builder;

class Specialty extends HospitalModel
{
    protected $primaryKey = 'specialty_id';

    protected $fillable = ['code', 'name', 'status'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
