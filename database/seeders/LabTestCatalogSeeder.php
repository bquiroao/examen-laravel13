<?php

namespace Database\Seeders;

use App\Models\Hospital\LabTest;
use App\Services\LabCatalog\LabTestCatalogService;
use App\Services\LabCatalog\LabTestOptions;

/**
 * Módulo catálogo de pruebas de laboratorio: el catálogo inicial de cada hospital. Son las 10 pruebas del catálogo
 * provisional (config/nido_catalogos.php) con sus requisitos de muestra. Conservan su código (HB, GLU…) porque las
 * órdenes, los resultados y el aviso de resultado crítico ya lo usan. Rangos de adulto, orientativos. Idempotente.
 */
class LabTestCatalogSeeder
{
    public const VALID_FROM = '2026-01-01';

    private const EDTA = ['sangre-total', 'Tubo tapa lila (EDTA)', '3', 0, null, 'refrigerada', 24];

    private const SERUM = ['suero', 'Tubo tapa roja o amarilla (con gel)', '2', 0, null, 'refrigerada', 24];

    /** [código, nombre, sección, horas de respuesta, muestra, unidad, ref. baja, ref. alta, crítico bajo, crítico alto] */
    private const TESTS = [
        ['HB', 'Hemoglobina', 'hematologia', 4, self::EDTA, 'g/dL', 12.0, 17.5, 7.0, 20.0],
        ['LEU', 'Leucocitos', 'hematologia', 4, self::EDTA, '10³/µL', 4.5, 11.0, 2.0, 30.0],
        ['PLT', 'Plaquetas', 'hematologia', 4, self::EDTA, '10³/µL', 150, 450, 50, 1000],
        ['GLU', 'Glucosa en ayunas', 'quimica-clinica', 2, ['suero', 'Tubo tapa roja o amarilla (con gel)', '2', 8, 'Ayuno de 8 horas; puede tomar agua.', 'refrigerada', 24],
            'mg/dL', 70, 100, 50, 400],
        ['CREA', 'Creatinina', 'quimica-clinica', 4, self::SERUM, 'mg/dL', 0.6, 1.3, null, 5.0],
        ['UREA', 'Nitrógeno ureico (BUN)', 'quimica-clinica', 4, self::SERUM, 'mg/dL', 7, 20, null, 100],
        ['NA', 'Sodio', 'quimica-clinica', 2, self::SERUM, 'mmol/L', 135, 145, 120, 160],
        ['K', 'Potasio', 'quimica-clinica', 2, ['suero', 'Tubo tapa roja o amarilla (con gel)', '2', 0, 'Evitar el torniquete prolongado: falsea el resultado.', 'refrigerada', 24],
            'mmol/L', 3.5, 5.1, 2.5, 6.5],
        ['PCR', 'Proteína C reactiva', 'inmunologia', 4, self::SERUM, 'mg/L', 0, 5, null, null],
        ['HBA1C', 'Hemoglobina glicosilada', 'quimica-clinica', 24, ['sangre-total', 'Tubo tapa lila (EDTA)', '2', 0, null, 'refrigerada', 168], '%', 4.0, 5.6, null, null],
    ];

    /** Se carga al instalar, antes de que el hospital tenga usuarios: el autor queda vacío (carga inicial). */
    public function run(): void
    {
        foreach (self::TESTS as [$code, $name, $section, $hours, [$specimen, $container, $volume, $fasting, $preparation, $storage, $stability], $unit, $low, $high, $critLow, $critHigh]) {
            if (LabTest::query()->where('code', $code)->exists()) {
                continue;
            }
            LabTest::query()->create([
                'code' => $code, 'version' => 1, 'name' => $name, 'section' => $section, 'turnaround_hours' => $hours, 'specimen_type' => $specimen,
                'container' => $container, 'min_volume_ml' => $volume, 'fasting_hours' => $fasting, 'preparation_instructions' => $preparation,
                'storage' => $storage, 'stability_hours' => $stability, 'unit' => $unit, 'ref_low' => $low, 'ref_high' => $high,
                'critical_low' => $critLow, 'critical_high' => $critHigh, 'initial_load' => true, 'valid_from' => self::VALID_FROM, 'created_at' => now(),
                'search_text' => LabTestCatalogService::normalize($code.' '.$name.' '.LabTestOptions::SECTIONS[$section]),
            ]);
        }
    }
}
