<?php

namespace Tests\Feature\Clinical;

use App\Models\Central\CentralUser;
use App\Models\Hospital\DoctorProfile;
use App\Models\Hospital\Specialty;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoClinicSeeder;
use Database\Seeders\HospitalCatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Platform\PlatformTestCase;

/** CENTRAL con la red de demostración + base HOSPITAL limpia con especialidades, médicos (con horario) y pacientes. */
abstract class ClinicalTestCase extends PlatformTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'hospital', '--path' => database_path('migrations/hospital'), '--realpath' => true, '--force' => true]);
        (new HospitalCatalogSeeder)->run();
        (new DemoClinicSeeder)->runFor($this->hospital(), appointments: false);
    }

    protected function specialty(string $code): string
    {
        return Specialty::query()->where('code', $code)->value('specialty_id');
    }

    protected function doctor(string $email): DoctorProfile
    {
        // Dos bases distintas: primero el id en CENTRAL, después el perfil en HOSPITAL.
        $userId = CentralUser::query()->where('email', $email)->value('user_id');

        return DoctorProfile::query()->whereHas('userRef', fn ($q) => $q->where('central_user_id', $userId))->firstOrFail();
    }

    /** Próximo lunes a la hora indicada (hora de Guatemala): siempre futuro y dentro del horario 08:00–12:00. */
    protected function nextMonday(int $hour, int $minute = 0): CarbonImmutable
    {
        return CarbonImmutable::now('America/Guatemala')->next(CarbonImmutable::MONDAY)->setTime($hour, $minute);
    }
}
