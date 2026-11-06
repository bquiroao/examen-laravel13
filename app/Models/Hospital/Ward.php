<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends HospitalModel
{
    protected $primaryKey = 'ward_id';

    protected $fillable = ['code', 'name', 'location', 'status', 'capacity'];

    protected $casts = ['capacity' => 'integer'];

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class, 'ward_id')->orderBy('code');
    }
}
