<?php

namespace App\Http\Resources;

use App\Models\Hospital\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->appointment_id,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'status' => $this->status,
            'reason' => $this->reason,
            'status_reason' => $this->status_reason,
            'next' => Appointment::TRANSITIONS[$this->status] ?? [],
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->local_patient_id, 'code' => $this->patient->code, 'name' => $this->patient->fullName(),
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => ['id' => $this->doctor->doctor_profile_id, 'name' => $this->doctor->name()]),
            'specialty' => $this->whenLoaded('specialty', fn () => ['id' => $this->specialty->specialty_id, 'name' => $this->specialty->name]),
        ];
    }
}
