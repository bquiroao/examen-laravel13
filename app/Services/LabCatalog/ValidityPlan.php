<?php

namespace App\Services\LabCatalog;

use App\Exceptions\DomainRuleException;
use App\Models\Hospital\LabTest;
use Carbon\CarbonImmutable;

/**
 * RN-05, separada del guardado (SRP, semana 2): qué pasa con la versión vigente cuando llega una nueva.
 *  - si la nueva empieza el mismo día que la vigente o antes, se superponen → 409;
 *  - si la vigente sigue abierta o termina el día de inicio de la nueva o después, se cierra el día previo;
 *  - si la vigente ya terminaba antes, queda como está.
 */
final class ValidityPlan
{
    private function __construct(public readonly ?string $closePreviousOn) {}

    public static function for(LabTest $current, string $from): self
    {
        $currentFrom = $current->valid_from->toDateString();
        if ($from <= $currentFrom) {
            throw new DomainRuleException('VIGENCIA_SUPERPUESTA', "La versión nueva debe empezar después del {$currentFrom}, cuando empezó la versión {$current->version}.", 409,
                ['vigencia' => ['desde' => $currentFrom, 'hasta' => $current->valid_to?->toDateString()]]);
        }
        $dayBefore = CarbonImmutable::parse($from)->subDay()->toDateString();

        return new self($current->valid_to === null || $current->valid_to->toDateString() >= $from ? $dayBefore : null);
    }
}
