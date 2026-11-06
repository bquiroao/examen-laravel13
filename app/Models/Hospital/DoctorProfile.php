<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorProfile extends HospitalModel
{
    protected $primaryKey = 'doctor_profile_id';

    protected $fillable = ['local_user_ref_id', 'license_number', 'status', 'valid_from', 'valid_to'];

    protected $casts = ['valid_from' => 'date:Y-m-d', 'valid_to' => 'date:Y-m-d'];

    public function userRef(): BelongsTo
    {
        return $this->belongsTo(LocalUserRef::class, 'local_user_ref_id');
    }

    public function specialties(): HasMany
    {
        return $this->hasMany(DoctorSpecialty::class, 'doctor_profile_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class, 'doctor_profile_id')->orderBy('weekday')->orderBy('starts_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('valid_from', '<=', now()->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()->toDateString()));
    }

    /** Perfil de médico de un usuario de la base central en este hospital. */
    public function scopeOfUser(Builder $query, string $centralUserId): Builder
    {
        return $query->whereHas('userRef', fn (Builder $q) => $q->where('central_user_id', $centralUserId));
    }

    public function name(): string
    {
        return (string) $this->userRef?->display_name;
    }
}
