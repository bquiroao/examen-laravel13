<?php

namespace App\Services\Medications;

use Illuminate\Support\Str;

/**
 * Cómo se comparan los medicamentos entre sí y con lo que se busca:
 *  - RN-04: dos fichas son equivalentes si coinciden principio activo, concentración, unidad, forma y vía. El principio
 *    activo se compara sin mayúsculas, tildes ni espacios repetidos: «Amoxicilina » y «amoxicilina» son el mismo.
 *  - RN-03: la búsqueda usa el mismo texto normalizado, y % y _ se buscan como letras, no como comodines.
 */
final class EquivalenceKey
{
    /** @param array{active_ingredient: string, strength_value: string|int|float, strength_unit: string, dosage_form: string, route: string} $data */
    public static function of(array $data): string
    {
        return implode('|', [self::normalize($data['active_ingredient']), self::strength($data['strength_value']), $data['strength_unit'], $data['dosage_form'], $data['route']]);
    }

    /** 500, «500.0» y «500.000» son la misma concentración. */
    public static function strength(string|int|float $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }

    public static function normalize(?string $text): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim((string) $text)) ?? ''));
    }

    public static function searchText(string $code, string $name, string $activeIngredient, ?string $legacyCode): string
    {
        return self::normalize(implode(' ', array_filter([$code, $name, $activeIngredient, $legacyCode])));
    }

    /** Patrón LIKE para «contiene»: escapa \, % y _ para que se busquen tal cual (RN-03). */
    public static function likePattern(string $query): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], self::normalize($query)).'%';
    }

    /**
     * Familias de alergia que se reconocen en el principio activo, para sugerirlas al registrar
     * (p. ej. amoxicilina → penicilinas). Quien registra puede cambiarlas.
     *
     * @return list<string>
     */
    public static function suggestAllergyGroups(string $activeIngredient): array
    {
        $text = self::normalize($activeIngredient);

        return collect(config('nido_catalogos.familias_alergia', []))
            ->filter(fn (array $words) => collect($words)->contains(fn (string $w) => str_contains($text, $w)))->keys()->values()->all();
    }
}
