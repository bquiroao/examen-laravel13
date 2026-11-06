<?php

namespace Tests\Feature\Clinical;

use App\Models\Central\CentralUser;
use App\Models\Hospital\Appointment;
use App\Models\Hospital\LocalPatient;
use App\Services\Hospital\HospitalDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;

#[Group('clinical')]
class DoctorAndAppointmentTest extends ClinicalTestCase
{
    private function patientId(string $family): string
    {
        return LocalPatient::query()->where('family_name', $family)->value('local_patient_id');
    }

    private function book(array $headers, string $family, string $doctorEmail, string $specialty, string $startsAt, string $reason = 'Consulta'): TestResponse
    {
        return $this->postJson('/api/v1/citas', ['patient_id' => $this->patientId($family), 'doctor_id' => $this->doctor($doctorEmail)->doctor_profile_id,
            'specialty_id' => $this->specialty($specialty), 'starts_at' => $startsAt, 'reason' => $reason], $headers);
    }

    public function test_registering_a_doctor_needs_the_doctor_role_and_a_unique_license(): void
    {
        $admin = $this->as('admin.hospital@nido.test');
        $nurse = CentralUser::query()->where('email', 'enfermeria@nido.test')->value('user_id');
        $this->postJson('/api/v1/membresias', ['user_id' => $nurse, 'role' => 'medico'], $admin)->assertCreated();

        $this->getJson('/api/v1/medicos/candidatos', $admin)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $nurse);
        $farmacia = CentralUser::query()->where('email', 'farmacia@nido.test')->value('user_id');
        $this->postJson('/api/v1/medicos', ['user_id' => $farmacia, 'license_number' => 'COL-9', 'specialty_id' => $this->specialty('PEDIATRIA')], $admin)
            ->assertStatus(422)->assertJsonPath('code', 'USUARIO_NO_ES_MEDICO');
        $taken = $this->doctor('medico@nido.test')->license_number;
        $this->postJson('/api/v1/medicos', ['user_id' => $nurse, 'license_number' => strtolower($taken), 'specialty_id' => $this->specialty('PEDIATRIA')], $admin)
            ->assertStatus(409)->assertJsonPath('code', 'COLEGIADO_DUPLICADO');
        $this->postJson('/api/v1/medicos', ['user_id' => $nurse, 'license_number' => 'col-77123', 'specialty_id' => $this->specialty('PEDIATRIA')], $admin)
            ->assertCreated()->assertJsonPath('data.license_number', 'COL-77123')->assertJsonPath('data.specialties.0.is_primary', true);
    }

    public function test_schedule_blocks_must_be_half_hours_without_overlaps(): void
    {
        $admin = $this->as('admin.hospital@nido.test');
        $id = $this->doctor('pediatria@nido.test')->doctor_profile_id;

        $this->putJson("/api/v1/medicos/{$id}/horario", ['blocks' => [['weekday' => 1, 'starts_at' => '08:15', 'ends_at' => '10:00']]], $admin)
            ->assertStatus(422)->assertJsonPath('code', 'HORARIO_INVALIDO');
        $this->putJson("/api/v1/medicos/{$id}/horario", ['blocks' => [['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '10:00'], ['weekday' => 1, 'starts_at' => '09:30', 'ends_at' => '11:00']]], $admin)
            ->assertStatus(422)->assertJsonPath('code', 'HORARIO_SUPERPUESTO');
        $this->putJson("/api/v1/medicos/{$id}/horario", ['blocks' => [['weekday' => 2, 'starts_at' => '07:00', 'ends_at' => '13:30']]], $admin)
            ->assertOk()->assertJsonPath('data.hours_per_week', 6.5);
    }

    public function test_specialty_catalog_counts_doctors_and_rejects_duplicates(): void
    {
        $admin = $this->as('admin.hospital@nido.test');

        $this->getJson('/api/v1/especialidades', $this->as('recepcion@nido.test'))->assertOk()
            ->assertJsonFragment(['name' => 'Pediatría', 'doctors' => 1])->assertJsonFragment(['name' => 'Medicina interna', 'doctors' => 2]);
        $this->postJson('/api/v1/especialidades', ['name' => 'Neurología'], $admin)->assertCreated()->assertJsonPath('data.code', 'NEUROLOGIA');
        $this->postJson('/api/v1/especialidades', ['name' => 'neurologia'], $admin)->assertStatus(409)->assertJsonPath('code', 'ESPECIALIDAD_EXISTENTE');
    }

    public function test_second_primary_specialty_is_rejected(): void
    {
        $id = $this->doctor('medico@nido.test')->doctor_profile_id;
        $this->postJson("/api/v1/medicos/{$id}/especialidades", ['specialty_id' => $this->specialty('DERMATOLOGIA'), 'is_primary' => true], $this->as('admin.hospital@nido.test'))
            ->assertStatus(409)->assertJsonPath('code', 'ESPECIALIDAD_DUPLICADA');
    }

    public function test_availability_lists_free_half_hours_and_booking_takes_one(): void
    {
        $headers = $this->as('recepcion@nido.test');
        $monday = $this->nextMonday(10);
        $query = '/api/v1/disponibilidad?especialidad='.$this->specialty('PEDIATRIA').'&desde='.$monday->toDateString();

        $before = $this->getJson($query, $headers)->assertOk()->json('data');
        $this->assertCount(14, $before, 'Pediatría: 08:00–12:00 y 14:00–17:00 son 14 franjas de 30 minutos');
        $this->book($headers, 'Morales Díaz', 'pediatria@nido.test', 'PEDIATRIA', $monday->toIso8601String(), 'Control de niño sano')
            ->assertCreated()->assertJsonPath('data.status', 'scheduled')->assertJsonPath('data.patient.name', 'Ana Lucía Morales Díaz');

        $after = collect($this->getJson($query, $headers)->json('data'));
        $this->assertCount(13, $after);
        $this->assertFalse($after->contains('starts_at', $monday->toIso8601String()));
    }

    public function test_booking_rules_from_the_appointments_module(): void
    {
        $headers = $this->as('recepcion@nido.test');
        $at = $this->nextMonday(9)->toIso8601String();
        $this->book($headers, 'Pérez López', 'medico@nido.test', 'MEDICINA_GENERAL', $at)->assertCreated();

        $this->book($headers, 'Gómez Ruiz', 'medico@nido.test', 'MEDICINA_GENERAL', $at)->assertStatus(409)->assertJsonPath('code', 'MEDICO_OCUPADO');
        $this->book($headers, 'Pérez López', 'cardiologia@nido.test', 'CARDIOLOGIA', $at)->assertStatus(409)->assertJsonPath('code', 'PACIENTE_OCUPADO');
        $this->book($headers, 'Gómez Ruiz', 'medico@nido.test', 'MEDICINA_GENERAL', $this->nextMonday(12, 30)->toIso8601String())
            ->assertStatus(422)->assertJsonPath('code', 'FUERA_DE_HORARIO');
        $this->book($headers, 'Gómez Ruiz', 'medico@nido.test', 'PEDIATRIA', $this->nextMonday(10)->toIso8601String())
            ->assertStatus(422)->assertJsonPath('code', 'ESPECIALIDAD_NO_VIGENTE');
        $this->book($headers, 'Gómez Ruiz', 'medico@nido.test', 'MEDICINA_GENERAL', now()->subHour()->toIso8601String())
            ->assertStatus(422)->assertJsonPath('code', 'HORARIO_PASADO');
    }

    public function test_status_changes_follow_the_allowed_transitions(): void
    {
        $doctor = $this->as('medico@nido.test');
        $id = $this->book($this->as('recepcion@nido.test'), 'Pérez López', 'medico@nido.test', 'MEDICINA_GENERAL', $this->nextMonday(11)->toIso8601String())->json('data.id');

        $this->postJson("/api/v1/citas/{$id}/estado", ['status' => 'completed'], $doctor)->assertStatus(422)->assertJsonPath('code', 'TRANSICION_INVALIDA');
        $this->postJson("/api/v1/citas/{$id}/estado", ['status' => 'confirmed'], $doctor)->assertOk()->assertJsonPath('data.next', ['completed', 'cancelled']);
        $this->postJson("/api/v1/citas/{$id}/estado", ['status' => 'completed'], $doctor)->assertStatus(422)->assertJsonPath('code', 'CITA_NO_TERMINADA');
        $this->postJson("/api/v1/citas/{$id}/estado", ['status' => 'cancelled'], $doctor)->assertStatus(422)->assertJsonPath('code', 'MOTIVO_REQUERIDO');
        $this->postJson("/api/v1/citas/{$id}/estado", ['status' => 'cancelled', 'reason' => 'El paciente avisó que no puede'], $doctor)
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.next', []);
    }

    public function test_a_past_confirmed_appointment_can_be_marked_as_attended(): void
    {
        $doctor = $this->doctor('medico@nido.test');
        $appointment = Appointment::query()->create(['local_patient_id' => $this->patientId('Gómez Ruiz'), 'doctor_profile_id' => $doctor->doctor_profile_id,
            'specialty_id' => $this->specialty('MEDICINA_GENERAL'), 'starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(30),
            'status' => 'confirmed', 'reason' => 'Control']);

        $this->postJson("/api/v1/citas/{$appointment->appointment_id}/estado", ['status' => 'completed'], $this->as('medico@nido.test'))
            ->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_agenda_lists_a_day_with_patient_doctor_and_specialty(): void
    {
        $headers = $this->as('recepcion@nido.test');
        $monday = $this->nextMonday(10);
        $this->book($headers, 'Morales Díaz', 'pediatria@nido.test', 'PEDIATRIA', $monday->toIso8601String());

        $this->getJson('/api/v1/citas?desde='.$monday->toDateString(), $headers)->assertOk()
            ->assertJsonFragment(['name' => 'Ana Lucía Morales Díaz'])->assertJsonFragment(['name' => 'Pediatría']);
    }

    public function test_a_doctor_only_sees_and_changes_their_own_appointments(): void
    {
        $reception = $this->as('recepcion@nido.test');
        $doctor = $this->as('medico@nido.test');
        $monday = $this->nextMonday(10);
        $own = $this->book($reception, 'Pérez López', 'medico@nido.test', 'MEDICINA_GENERAL', $monday->toIso8601String())->json('data.id');
        $other = $this->book($reception, 'Morales Díaz', 'pediatria@nido.test', 'PEDIATRIA', $monday->toIso8601String())->json('data.id');
        $day = '/api/v1/citas?desde='.$monday->toDateString();

        $this->getJson($day, $doctor)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own)
            ->assertJsonPath('solo_medico', $this->doctor('medico@nido.test')->name());
        $this->getJson($day.'&medico='.$this->doctor('pediatria@nido.test')->doctor_profile_id, $doctor)
            ->assertStatus(403)->assertJsonPath('code', 'SIN_PERMISO');
        $this->postJson("/api/v1/citas/{$other}/estado", ['status' => 'confirmed'], $doctor)
            ->assertStatus(403)->assertJsonPath('code', 'CITA_DE_OTRO_MEDICO');
        $this->assertSame('scheduled', Appointment::query()->find($other)->status);

        $this->getJson($day, $reception)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('solo_medico', null);
    }

    public function test_each_hospital_reads_its_own_clinical_database(): void
    {
        $databases = app(HospitalDatabase::class);
        $north = $this->hospital('HN-NOR');
        $default = config('database.connections.hospital.database');

        $this->assertSame($default, $databases->databaseFor($north));
        $north->forceFill(['metadata' => ['database' => 'his_hospital_nor']])->save();
        $this->assertSame('his_hospital_nor', $databases->databaseFor($north->fresh()));
        $north->forceFill(['metadata' => ['database' => 'x; DROP DATABASE y']])->save();
        $this->assertSame($default, $databases->databaseFor($north->fresh()), 'Un nombre inválido no se usa como base');
        $this->assertSame($default, DB::connection('hospital')->getDatabaseName());
    }
}
