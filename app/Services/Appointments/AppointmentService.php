<?php

namespace App\Services\Appointments;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\Appointment;
use App\Models\Hospital\DoctorProfile;
use App\Models\Hospital\DoctorSpecialty;
use App\Models\Hospital\LocalPatient;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Módulo citas médicas: E2 horario futuro y motivo, E3 especialidad vigente, E4/E5 sin cruces de médico ni de
 * paciente, E6 otra solicitud ganó el horario (la exclusión de la base es la última barrera), E7 transiciones,
 * R-09 el médico solo ve y cambia sus propias citas.
 */
class AppointmentService
{
    public function __construct(private readonly AvailabilityService $availability, private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    public function book(CentralUser $actor, Hospital $hospital, string $patientId, string $doctorId, string $specialtyId, string $startsAt, string $reason, ?string $ip): Appointment
    {
        $patient = LocalPatient::query()->find($patientId);
        if ($patient === null || $patient->status !== 'active') {
            throw new DomainRuleException('PACIENTE_NO_DISPONIBLE', 'El paciente no existe o su ficha no está activa.');
        }
        $doctor = DoctorProfile::query()->active()->find($doctorId) ?? throw new DomainRuleException('MEDICO_NO_DISPONIBLE', 'El médico no existe o no está activo.');
        if (trim($reason) === '') {
            throw new DomainRuleException('MOTIVO_REQUERIDO', 'Escriba el motivo de la consulta.');
        }
        $start = CarbonImmutable::parse($startsAt)->utc()->startOfMinute();
        $end = $start->addMinutes(AvailabilityService::MINUTES);
        if ($start <= CarbonImmutable::now()) {
            throw new DomainRuleException('HORARIO_PASADO', 'La cita debe ser en un horario futuro.');
        }
        $date = $start->setTimezone($hospital->timezone ?: config('app.timezone'))->toDateString();
        if (! DoctorSpecialty::query()->where('doctor_profile_id', $doctorId)->where('specialty_id', $specialtyId)->validOn($date)->exists()) {
            throw new DomainRuleException('ESPECIALIDAD_NO_VIGENTE', 'El médico no atiende esa especialidad en esa fecha.');
        }
        if (! $this->availability->withinSchedule($hospital, $doctor, $start, $end)) {
            throw new DomainRuleException('FUERA_DE_HORARIO', 'El médico no atiende a esa hora. Elija una de las franjas libres.');
        }
        $overlap = fn (string $column, string $id) => Appointment::query()->where($column, $id)->whereIn('status', Appointment::ACTIVE)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
        if ($overlap('doctor_profile_id', $doctorId)) {
            throw new DomainRuleException('MEDICO_OCUPADO', 'El médico ya tiene una cita en ese horario.', 409);
        }
        if ($overlap('local_patient_id', $patientId)) {
            throw new DomainRuleException('PACIENTE_OCUPADO', 'El paciente ya tiene otra cita a esa hora.', 409);
        }

        try {
            $appointment = DB::connection('hospital')->transaction(fn () => Appointment::query()->create([
                'local_patient_id' => $patientId, 'doctor_profile_id' => $doctorId, 'specialty_id' => $specialtyId,
                'starts_at' => $start, 'ends_at' => $end, 'status' => 'scheduled', 'reason' => trim($reason),
                'booked_by_user_ref_id' => $this->refs->forUser($actor, $hospital)->local_user_ref_id,
            ]));
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                throw new DomainRuleException('HORARIO_TOMADO', 'Otra persona acaba de tomar ese horario. Elija otro.', 409);
            }
            throw $e;
        }
        $this->audit->record('citas', 'CITA_PROGRAMADA', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
            ['cita' => $appointment->appointment_id, 'inicio' => $start->toIso8601String()], $ip);

        return $appointment;
    }

    /** R-09: si el usuario es médico de este hospital, su agenda se limita a sus citas. Null: ve las de todos. */
    public function ownDoctor(CentralUser $user): ?DoctorProfile
    {
        return DoctorProfile::query()->with('userRef')->ofUser($user->user_id)->first();
    }

    public function transition(CentralUser $actor, Hospital $hospital, Appointment $appointment, string $to, string $reason, ?string $ip): Appointment
    {
        $own = $this->ownDoctor($actor);
        if ($own !== null && $own->doctor_profile_id !== $appointment->doctor_profile_id) {
            throw new DomainRuleException('CITA_DE_OTRO_MEDICO', 'Solo puede cambiar sus propias citas.', 403);
        }
        $from = $appointment->status;
        if (! in_array($to, Appointment::TRANSITIONS[$from] ?? [], true)) {
            throw new DomainRuleException('TRANSICION_INVALIDA', 'Una cita «'.self::label($from).'» no puede pasar a «'.self::label($to).'».');
        }
        if ($to === 'cancelled' && trim($reason) === '') {
            throw new DomainRuleException('MOTIVO_REQUERIDO', 'Escriba por qué se cancela la cita.');
        }
        if ($to === 'completed' && $appointment->ends_at > now()) {
            throw new DomainRuleException('CITA_NO_TERMINADA', 'Solo se marca como atendida cuando terminó su horario ('.$appointment->ends_at->setTimezone($hospital->timezone)->format('H:i').').');
        }
        $updated = Appointment::query()->whereKey($appointment->getKey())->where('status', $from)->update([
            'status' => $to, 'status_reason' => trim($reason) ?: null, 'status_changed_at' => now(), 'updated_at' => now(),
        ]);
        if ($updated === 0) {
            throw new DomainRuleException('CITA_CAMBIO', 'La cita cambió mientras tanto. Recargue la agenda.', 409);
        }
        $this->audit->record('citas', 'CITA_'.strtoupper(self::label($to, true)), 'exito', $hospital->hospital_id, $actor->user_id,
            'paciente:'.$appointment->patient->code, ['cita' => $appointment->appointment_id] + ($reason !== '' ? ['motivo' => trim($reason)] : []), $ip);

        return $appointment->fresh();
    }

    public static function label(string $status, bool $code = false): string
    {
        $labels = ['scheduled' => 'programada', 'confirmed' => 'confirmada', 'completed' => 'atendida', 'cancelled' => 'cancelada'];

        return $code ? ($labels[$status] ?? $status) : ucfirst($labels[$status] ?? $status);
    }
}
