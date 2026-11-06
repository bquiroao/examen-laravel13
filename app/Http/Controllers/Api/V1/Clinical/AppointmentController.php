<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Hospital\Appointment;
use App\Services\Appointments\AppointmentService;
use App\Services\Appointments\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Módulo citas médicas: agenda del día o de un rango (el médico solo ve la suya, R-09), franjas libres, programar y cambiar el estado. */
class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'medico' => ['nullable', 'uuid'],
            'paciente' => ['nullable', 'uuid'],
            'estado' => ['nullable', 'in:scheduled,confirmed,completed,cancelled'],
        ]);
        $tz = $request->attributes->get('hospital')->timezone ?: config('app.timezone');
        $from = CarbonImmutable::parse($data['desde'], $tz)->startOfDay();
        $to = CarbonImmutable::parse($data['hasta'] ?? $data['desde'], $tz)->endOfDay();
        abort_if($from->diffInDays($to) > 31, 422, 'Consulte como máximo 31 días a la vez.');
        $own = $this->appointments->ownDoctor($request->user());
        if ($own !== null && ($data['medico'] ?? $own->doctor_profile_id) !== $own->doctor_profile_id) {
            throw new DomainRuleException('SIN_PERMISO', 'Solo puede consultar sus propias citas.', 403);
        }

        return AppointmentResource::collection(Appointment::query()->with(['patient', 'doctor.userRef', 'specialty'])
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->when($own?->doctor_profile_id ?? $data['medico'] ?? null, fn ($q, $v) => $q->where('doctor_profile_id', $v))
            ->when($data['paciente'] ?? null, fn ($q, $v) => $q->where('local_patient_id', $v))
            ->when($data['estado'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('starts_at')->limit(500)->get())->additional(['solo_medico' => $own?->name()]);
    }

    public function availability(Request $request, AvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'especialidad' => ['required', 'uuid'],
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'medico' => ['nullable', 'uuid'],
        ], [], ['especialidad' => 'especialidad']);

        return response()->json(['data' => $availability->slots($request->attributes->get('hospital'), $data['especialidad'], $data['desde'],
            $data['hasta'] ?? $data['desde'], $data['medico'] ?? null)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'uuid'],
            'doctor_id' => ['required', 'uuid'],
            'specialty_id' => ['required', 'uuid'],
            'starts_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:300', 'regex:/\S/'],
        ], [], ['patient_id' => 'paciente', 'doctor_id' => 'médico', 'specialty_id' => 'especialidad', 'starts_at' => 'horario', 'reason' => 'motivo']);
        $appointment = $this->appointments->book($request->user(), $request->attributes->get('hospital'), $data['patient_id'], $data['doctor_id'],
            $data['specialty_id'], $data['starts_at'], $data['reason'], $request->ip());

        return (new AppointmentResource($appointment->load(['patient', 'doctor.userRef', 'specialty'])))->response()->setStatusCode(201);
    }

    public function transition(Request $request, string $appointment): AppointmentResource
    {
        $data = $request->validate(['status' => ['required', 'in:confirmed,completed,cancelled'], 'reason' => ['nullable', 'string', 'max:300']], [], ['status' => 'estado', 'reason' => 'motivo']);
        $model = Appointment::query()->with('patient')->find($appointment) ?? abort(404, 'Cita no encontrada.');

        return new AppointmentResource($this->appointments->transition($request->user(), $request->attributes->get('hospital'), $model,
            $data['status'], (string) ($data['reason'] ?? ''), $request->ip())->load(['patient', 'doctor.userRef', 'specialty']));
    }
}
