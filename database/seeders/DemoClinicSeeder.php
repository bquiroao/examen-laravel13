<?php

namespace Database\Seeders;

use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Central\HospitalMembership;
use App\Models\Hospital\Allergy;
use App\Models\Hospital\Appointment;
use App\Models\Hospital\Bed;
use App\Models\Hospital\BedStay;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\DoctorProfile;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\Specialty;
use App\Models\Hospital\Ward;
use App\Services\Hospital\UserRefSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos clínicos de demostración para la base del hospital actual: perfiles de los médicos que tienen el rol
 * «Médico» en ese hospital, su horario, pacientes ficticios y algunas citas futuras. Idempotente.
 */
class DemoClinicSeeder
{
    /** Pacientes ficticios. Los DPI son inventados con un formato válido (departamento 01–22). */
    private const PATIENTS = [
        ['María José', 'Pérez López', '1985-03-14', 'female', '2456789120101'],
        ['Carlos Enrique', 'Gómez Ruiz', '1972-11-02', 'male', '1987654320108'],
        ['Ana Lucía', 'Morales Díaz', '2016-06-21', 'female', null],
        ['José Antonio', 'Hernández Castillo', '1958-01-30', 'male', '3012456780901'],
        ['Sofía Isabel', 'Ramírez Ortiz', '1999-09-09', 'female', '2789012340115'],
        ['Luis Fernando', 'Castillo Méndez', '1990-12-25', 'male', '2234567890112'],
        ['Gabriela', 'Juárez Fuentes', '2003-04-18', 'female', '3345678900122'],
        ['Pedro Pablo', 'Xitumul Coc', '1966-07-07', 'male', '1765432100816'],
        ['Valeria', 'Santos Aguilar', '2020-02-29', 'female', null],
        ['Miguel Ángel', 'Rodríguez Chávez', '1978-05-05', 'male', '2109876540104'],
    ];

    public function runFor(Hospital $hospital, bool $appointments = true): void
    {
        $refs = app(UserRefSync::class);
        $specialties = Specialty::query()->pluck('specialty_id', 'code');
        $doctorIds = HospitalMembership::query()->current()->where('hospital_id', $hospital->hospital_id)->where('role_code', 'medico')->pluck('user_id');
        $plan = ['medico@nido.test' => ['MEDICINA_GENERAL', 'MEDICINA_INTERNA'], 'pediatria@nido.test' => ['PEDIATRIA'],
            'cardiologia@nido.test' => ['CARDIOLOGIA', 'MEDICINA_INTERNA']];

        $profiles = [];
        foreach (CentralUser::query()->whereIn('user_id', $doctorIds)->orderBy('email')->get() as $i => $user) {
            $ref = $refs->forUser($user, $hospital, 'medico');
            $license = 'COL-'.$hospital->code.'-'.str_pad((string) (1001 + $i), 5, '0', STR_PAD_LEFT);
            // Si la base del hospital ya tenía datos de una instalación anterior, se reutiliza el perfil existente.
            $profile = DoctorProfile::query()->where('local_user_ref_id', $ref->local_user_ref_id)->first()
                ?? DoctorProfile::query()->where('license_number', $license)->first()
                ?? DoctorProfile::query()->create(['local_user_ref_id' => $ref->local_user_ref_id, 'license_number' => $license, 'status' => 'active',
                    'valid_from' => now()->subYear()->toDateString()]);
            $codes = $plan[$user->email] ?? ['MEDICINA_GENERAL'];
            foreach ($codes as $j => $code) {
                $profile->specialties()->firstOrCreate(['specialty_id' => $specialties[$code]],
                    ['is_primary' => $j === 0, 'valid_from' => now()->subYear()->toDateString(), 'status' => 'active']);
            }
            if (! $profile->schedules()->exists()) {
                foreach (range(1, 5) as $day) {
                    $profile->schedules()->create(['weekday' => $day, 'starts_at' => '08:00', 'ends_at' => '12:00']);
                    $profile->schedules()->create(['weekday' => $day, 'starts_at' => '14:00', 'ends_at' => '17:00']);
                }
            }
            $profiles[] = [$profile, $codes[0]];
        }

        $patients = [];
        foreach (self::PATIENTS as [$given, $family, $birth, $sex, $dpi]) {
            $patients[] = LocalPatient::query()->where('search_name', LocalPatient::normalize("$given $family"))->first()
                ?? LocalPatient::query()->create([
                    'global_id' => (string) Str::uuid(),
                    'code' => $hospital->code.'-'.str_pad((string) DB::connection('hospital')->scalar("SELECT nextval('local_patient_code_seq')"), 6, '0', STR_PAD_LEFT),
                    'given_name' => $given, 'family_name' => $family, 'birth_date' => $birth, 'sex' => $sex,
                    'document_type' => $dpi ? 'DPI' : null, 'document_number' => $dpi,
                    'contact' => '5'.substr((string) crc32($given.$family), 0, 7), 'address' => 'Ciudad de Guatemala',
                    'search_name' => LocalPatient::normalize("$given $family"), 'status' => 'active', 'mpi_link_status' => 'pending', 'version' => 1,
                ]);
        }

        $this->seedBeds($hospital, $patients, $refs);

        // Algunas citas en los próximos días hábiles, dentro del horario.
        if ($appointments && $profiles !== [] && ! Appointment::query()->exists()) {
            $tz = $hospital->timezone ?: 'America/Guatemala';
            $day = CarbonImmutable::now($tz)->addDay()->startOfDay();
            while ($day->isWeekend()) {
                $day = $day->addDay();
            }
            $booker = $refs->forUser(CentralUser::query()->whereIn('user_id', $doctorIds)->orderBy('email')->firstOrFail(), $hospital);
            $reasons = ['Control de presión arterial', 'Dolor de cabeza frecuente', 'Control de niño sano', 'Revisión de exámenes', 'Dolor en el pecho al caminar', 'Control prenatal'];
            foreach (array_slice($patients, 0, 6) as $n => $patient) {
                [$profile, $code] = $profiles[$n % count($profiles)];
                $start = $day->setTime(8 + intdiv($n, count($profiles)), 0)->utc();
                Appointment::query()->create([
                    'local_patient_id' => $patient->local_patient_id, 'doctor_profile_id' => $profile->doctor_profile_id, 'specialty_id' => $specialties[$code],
                    'starts_at' => $start, 'ends_at' => $start->addMinutes(30), 'status' => $n % 3 === 0 ? 'confirmed' : 'scheduled',
                    'reason' => $reasons[$n], 'booked_by_user_ref_id' => $booker->local_user_ref_id,
                ]);
            }
        }
    }

    /**
     * Salas y camas, y tres pacientes internados. Los internamientos son datos de ejemplo cargados directamente:
     * el flujo real de admisión lo integra su propio módulo.
     *
     * @param  list<LocalPatient>  $patients
     */
    private function seedBeds(Hospital $hospital, array $patients, UserRefSync $refs): void
    {
        if (Ward::query()->exists()) {
            return;
        }
        $layout = [
            ['MED-1', 'Medicina interna', 'Segundo nivel, ala norte', 8, 'standard', ['201', '202', '203', '204', '205', '206']],
            ['CIR-1', 'Cirugía', 'Tercer nivel', 6, 'standard', ['301', '302', '303', '304']],
            ['PED-1', 'Pediatría', 'Primer nivel', 6, 'pediatrica', ['101', '102', '103', '104']],
            ['UCI', 'Cuidados intensivos', 'Segundo nivel, ala sur', 4, 'uci', ['U-1', 'U-2', 'U-3']],
        ];
        $beds = [];
        foreach ($layout as [$code, $name, $location, $capacity, $type, $codes]) {
            $ward = Ward::query()->create(['code' => $code, 'name' => $name, 'location' => $location, 'capacity' => $capacity, 'status' => 'active']);
            foreach ($codes as $bedCode) {
                $beds[$code.'/'.$bedCode] = Bed::query()->create(['ward_id' => $ward->ward_id, 'code' => $bedCode, 'type' => $type, 'status' => 'available']);
            }
        }
        $beds['CIR-1/304']->forceFill(['status' => 'maintenance', 'status_reason' => 'Reparación del respaldo'])->save();

        $doctorId = HospitalMembership::query()->current()->where('hospital_id', $hospital->hospital_id)->where('role_code', 'medico')->value('user_id');
        if ($doctorId === null) {
            return;
        }
        $ref = $refs->forUser(CentralUser::query()->findOrFail($doctorId), $hospital);
        // Alergias de ejemplo para ver el bloqueo de prescripciones (el registro de alergias es su propio módulo).
        Allergy::query()->create(['local_patient_id' => $patients[0]->local_patient_id, 'verified_by_user_ref_id' => $ref->local_user_ref_id,
            'allergen_name' => 'Penicilina', 'reaction' => 'Urticaria y dificultad respiratoria', 'allergy_type' => 'allergy', 'severity' => 'severe', 'status' => 'active']);
        Allergy::query()->create(['local_patient_id' => $patients[1]->local_patient_id, 'verified_by_user_ref_id' => $ref->local_user_ref_id,
            'allergen_name' => 'Ibuprofeno', 'reaction' => 'Malestar gástrico', 'allergy_type' => 'intolerance', 'severity' => 'mild', 'status' => 'active']);
        foreach ([[3, 'MED-1/201', 'Neumonía adquirida en la comunidad', 3], [7, 'CIR-1/301', 'Postoperatorio de apendicectomía', 1], [2, 'PED-1/101', 'Deshidratación moderada', 2]] as [$n, $bed, $reason, $days]) {
            $since = now()->subDays($days);
            $episode = CareEpisode::query()->create(['local_patient_id' => $patients[$n]->local_patient_id, 'type' => 'inpatient', 'status' => 'open',
                'started_at' => $since, 'reason' => $reason]);
            BedStay::query()->create(['care_episode_id' => $episode->care_episode_id, 'bed_id' => $beds[$bed]->bed_id,
                'assigned_by_user_ref_id' => $ref->local_user_ref_id, 'started_at' => $since, 'reason' => 'Ingreso']);
            $beds[$bed]->forceFill(['status' => 'occupied'])->save();
        }
    }
}
