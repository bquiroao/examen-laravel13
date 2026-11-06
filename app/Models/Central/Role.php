<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $connection = 'central';

    protected $primaryKey = 'role_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['role_code', 'name', 'description', 'scope'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_code', 'permission_code')->withPivot('created_at');
    }
}
