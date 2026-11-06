<?php

namespace Database\Seeders;

use App\Models\Hospital\Specialty;
use Illuminate\Database\Seeder;

/** Especialidades, medicamentos y pruebas de laboratorio base de la base clínica del hospital actual. Idempotente. */
class HospitalCatalogSeeder extends Seeder
{
    public const SPECIALTIES = [
        'MEDICINA_GENERAL' => 'Medicina general',
        'MEDICINA_INTERNA' => 'Medicina interna',
        'PEDIATRIA' => 'Pediatría',
        'GINECOLOGIA' => 'Ginecología y obstetricia',
        'CIRUGIA_GENERAL' => 'Cirugía general',
        'TRAUMATOLOGIA' => 'Traumatología y ortopedia',
        'CARDIOLOGIA' => 'Cardiología',
        'DERMATOLOGIA' => 'Dermatología',
        'PSICOLOGIA' => 'Psicología clínica',
        'ODONTOLOGIA' => 'Odontología',
    ];

    public function run(): void
    {
        foreach (self::SPECIALTIES as $code => $name) {
            Specialty::query()->firstOrCreate(['code' => $code], ['name' => $name, 'status' => 'active']);
        }
        // Módulo catalogo-medicamentos: catálogo inicial de medicamentos del hospital.
        (new MedicationCatalogSeeder)->run();
        // Módulo catalogo-pruebas-laboratorio: catálogo inicial de pruebas del hospital.
        (new LabTestCatalogSeeder)->run();
    }
}
