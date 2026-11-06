<?php

namespace Database\Seeders;

use App\Models\Hospital\Medication;
use App\Services\Medications\EquivalenceKey;
use Illuminate\Support\Facades\DB;

/**
 * Módulo catálogo de medicamentos: el catálogo inicial de cada hospital. Son los 13 medicamentos del catálogo
 * provisional (config/nido_catalogos.php) con su ficha completa; conservan su código anterior (legacy_code), así las
 * recetas que ya existían siguen resolviendo. Los registros sanitarios son ficticios. Idempotente.
 */
class MedicationCatalogSeeder
{
    public const VALID_FROM = '2026-01-01';

    /** [código anterior, nombre, principio activo, concentración, unidad, forma, vía, presentación, grupo terapéutico, familias de alergia] */
    private const MEDICATIONS = [
        ['AMOX500', 'Amoxicilina 500 mg cápsula', 'Amoxicilina', '500', 'mg', 'CAPSULA', 'ORAL', 'Cápsula 500 mg', 'ANTIBIOTICO', ['penicilinas']],
        ['AMPI1G', 'Ampicilina 1 g inyectable', 'Ampicilina', '1', 'g', 'POLVO_INYECTABLE', 'INTRAVENOSA', 'Vial 1 g', 'ANTIBIOTICO', ['penicilinas']],
        ['PENBEN', 'Penicilina G benzatínica 1.2 MUI', 'Penicilina G benzatínica', '1.2', 'MUI', 'POLVO_INYECTABLE', 'INTRAMUSCULAR', 'Vial 1.2 MUI', 'ANTIBIOTICO', ['penicilinas']],
        ['CEFTRI1G', 'Ceftriaxona 1 g inyectable', 'Ceftriaxona', '1', 'g', 'POLVO_INYECTABLE', 'INTRAVENOSA', 'Vial 1 g', 'ANTIBIOTICO', ['cefalosporinas']],
        ['AZI500', 'Azitromicina 500 mg tableta', 'Azitromicina', '500', 'mg', 'TABLETA', 'ORAL', 'Tableta 500 mg', 'ANTIBIOTICO', ['macrolidos']],
        ['TMPSMX', 'Trimetoprim-sulfametoxazol 160/800 mg', 'Sulfametoxazol + trimetoprim', '800', 'mg', 'TABLETA', 'ORAL', 'Tableta', 'ANTIBIOTICO', ['sulfonamidas']],
        ['PARA500', 'Acetaminofén 500 mg tableta', 'Acetaminofén', '500', 'mg', 'TABLETA', 'ORAL', 'Tableta 500 mg', 'ANALGESICO', []],
        ['IBU400', 'Ibuprofeno 400 mg tableta', 'Ibuprofeno', '400', 'mg', 'TABLETA', 'ORAL', 'Tableta 400 mg', 'ANALGESICO', ['aines']],
        ['ASA100', 'Ácido acetilsalicílico 100 mg', 'Ácido acetilsalicílico', '100', 'mg', 'TABLETA', 'ORAL', 'Tableta 100 mg', 'CARDIOVASCULAR', ['aines']],
        ['OMEP20', 'Omeprazol 20 mg cápsula', 'Omeprazol', '20', 'mg', 'CAPSULA', 'ORAL', 'Cápsula 20 mg', 'GASTROINTESTINAL', []],
        ['METF850', 'Metformina 850 mg tableta', 'Metformina', '850', 'mg', 'TABLETA', 'ORAL', 'Tableta 850 mg', 'ENDOCRINO', []],
        ['LOSA50', 'Losartán 50 mg tableta', 'Losartán', '50', 'mg', 'TABLETA', 'ORAL', 'Tableta 50 mg', 'CARDIOVASCULAR', []],
        ['SALB', 'Salbutamol inhalador 100 mcg', 'Salbutamol', '100', 'mcg', 'INHALADOR', 'INHALATORIA', 'Inhalador', 'RESPIRATORIO', []],
    ];

    /** Se carga al instalar, antes de que el hospital tenga usuarios: el autor queda vacío (carga inicial). */
    public function run(): void
    {
        foreach (self::MEDICATIONS as [$legacy, $name, $ingredient, $strength, $unit, $form, $route, $presentation, $group, $families]) {
            if (Medication::query()->where('legacy_code', $legacy)->exists()) {
                continue;
            }
            $next = (int) DB::connection('hospital')->scalar('SELECT coalesce(max(substr(code, 5)::int), 0) + 1 FROM medications');
            $code = 'MED-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $sheet = ['active_ingredient' => $ingredient, 'strength_value' => $strength, 'strength_unit' => $unit, 'dosage_form' => $form, 'route' => $route];
            Medication::query()->create($sheet + [
                'code' => $code, 'version' => 1, 'name' => $name, 'strength_value' => EquivalenceKey::strength($strength), 'presentation' => $presentation,
                'sanitary_registration' => 'RS-FIC-'.$legacy, 'therapeutic_group' => $group, 'allergy_groups' => $families,
                'legacy_code' => $legacy, 'equivalence_key' => EquivalenceKey::of($sheet), 'search_text' => EquivalenceKey::searchText($code, $name, $ingredient, $legacy),
                'valid_from' => self::VALID_FROM, 'created_at' => now(),
            ]);
        }
    }
}
