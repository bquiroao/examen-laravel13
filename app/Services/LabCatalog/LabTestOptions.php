<?php

namespace App\Services\LabCatalog;

/**
 * Listas cerradas y límites del catálogo de pruebas (RN-02 y RN-03). Son los mismos que la base acepta en sus CHECK
 * (migración 2026_10_31_000500), así un dato fuera de rango da 422 y nunca un error de la base (defectos D-02 y D-03).
 */
final class LabTestOptions
{
    public const SECTIONS = [
        'hematologia' => 'Hematología', 'quimica-clinica' => 'Química clínica', 'microbiologia' => 'Microbiología',
        'inmunologia' => 'Inmunología', 'uroanalisis' => 'Uroanálisis', 'coprologia' => 'Coprología',
    ];

    public const SPECIMENS = [
        'sangre-total' => 'Sangre total', 'suero' => 'Suero', 'plasma' => 'Plasma', 'orina' => 'Orina', 'heces' => 'Heces', 'hisopado' => 'Hisopado',
    ];

    public const STORAGE = ['ambiente' => 'Temperatura ambiente', 'refrigerada' => 'Refrigerada (2–8 °C)', 'congelada' => 'Congelada'];

    public const CODE_PATTERN = '/^[A-Z]{3}-[0-9]{3}$/';

    public const NAME_MAX = 150;

    public const CONTAINER_MAX = 80;

    public const PREPARATION_MAX = 300;

    public const VOLUME_MAX = 9999.99;

    public const MAX_RESULTS = 100;

    /** Días antes del cierre en que la pantalla avisa «vence pronto» (cambio de la semana 12). */
    public const EXPIRING_DAYS = 7;

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $pairs = fn (array $map) => collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();

        return ['sections' => $pairs(self::SECTIONS), 'specimens' => $pairs(self::SPECIMENS), 'storage' => $pairs(self::STORAGE), 'expiring_days' => self::EXPIRING_DAYS];
    }
}
