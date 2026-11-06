<?php

namespace App\Services\Appointments;

use App\Exceptions\DomainRuleException;
use App\Models\Central\Hospital;
use App\Models\Hospital\Appointment;
use App\Models\Hospital\DoctorProfile;
use App\Models\Hospital\DoctorSpecialty;
use Carbon\CarbonImmutable;

/**
 * Módulo médicos-especialidades, CU-06/CU-07: franjas libres de 30 minutos por especialidad = horario semanal
 * del médico − citas activas. Solo médicos activos con la especialidad vigente ese día, y hasta 14 días.
 */
class AvailabilityService
{
    public const MINUTES = 30;

    public const MAX_DAYS = 14;

    /**
     * @return list<array{doctor_id: string, doctor: string, starts_at: string, ends_at: string}>
     */
    public function slots(Hospital $hospital, string $specialtyId, string $from, string $to, ?string $doctorId = null): array
    {
        $tz = $hospital->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();
        $start = CarbonImmutable::parse($from, $tz)->startOfDay();
        $end = CarbonImmutable::parse($to, $tz)->startOfDay();
        if ($start < $today || $end < $start || $start->diffInDays($end) >= self::MAX_DAYS) {
            throw new DomainRuleException('RANGO_INVALIDO', 'Elija fechas desde hoy y de hasta '.self::MAX_DAYS.' días.');
        }

        $doctors = DoctorProfile::query()->active()->with(['userRef', 'schedules'])
            ->when($doctorId, fn ($q) => $q->whereKey($doctorId))
            ->whereHas('specialties', fn ($q) => $q->where('specialty_id', $specialtyId)->where('status', 'active'))
            ->get()->keyBy('doctor_profile_id');
        if ($doctors->isEmpty()) {
            return [];
        }

        $links = DoctorSpecialty::query()->where('specialty_id', $specialtyId)->where('status', 'active')
            ->whereIn('doctor_profile_id', $doctors->keys())->get()->groupBy('doctor_profile_id');
        $busy = Appointment::query()->whereIn('doctor_profile_id', $doctors->keys())->whereIn('status', Appointment::ACTIVE)
            ->where('starts_at', '<', $end->addDay()->utc())->where('ends_at', '>', $start->utc())
            ->get(['doctor_profile_id', 'starts_at', 'ends_at'])->groupBy('doctor_profile_id');
        $now = CarbonImmutable::now();

        $slots = [];
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $date = $day->toDateString();
            foreach ($doctors as $id => $doctor) {
                $valid = ($links[$id] ?? collect())->contains(fn (DoctorSpecialty $l) => $l->valid_from->toDateString() <= $date
                    && ($l->valid_to === null || $l->valid_to->toDateString() > $date));
                if (! $valid) {
                    continue;
                }
                foreach ($doctor->schedules->where('weekday', $day->dayOfWeekIso) as $block) {
                    $blockEnd = CarbonImmutable::parse($date.' '.$block->ends_at, $tz);
                    for ($t = CarbonImmutable::parse($date.' '.$block->starts_at, $tz); $t->addMinutes(self::MINUTES) <= $blockEnd; $t = $t->addMinutes(self::MINUTES)) {
                        $slotEnd = $t->addMinutes(self::MINUTES);
                        $taken = ($busy[$id] ?? collect())->contains(fn (Appointment $a) => $a->starts_at < $slotEnd && $a->ends_at > $t);
                        if ($t > $now && ! $taken) {
                            $slots[] = ['doctor_id' => $id, 'doctor' => $doctor->name(), 'starts_at' => $t->toIso8601String(), 'ends_at' => $slotEnd->toIso8601String()];
                        }
                    }
                }
            }
        }
        usort($slots, fn ($a, $b) => [$a['starts_at'], $a['doctor']] <=> [$b['starts_at'], $b['doctor']]);

        return $slots;
    }

    /** ¿El rango cae completo dentro de un bloque del horario del médico ese día? */
    public function withinSchedule(Hospital $hospital, DoctorProfile $doctor, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        $tz = $hospital->timezone ?: config('app.timezone');
        $local = $startsAt->setTimezone($tz);
        $localEnd = $endsAt->setTimezone($tz);
        $date = $local->toDateString();

        return $doctor->schedules()->where('weekday', $local->dayOfWeekIso)->get()->contains(fn ($b) => CarbonImmutable::parse($date.' '.$b->starts_at, $tz) <= $local
            && CarbonImmutable::parse($date.' '.$b->ends_at, $tz) >= $localEnd);
    }
}
