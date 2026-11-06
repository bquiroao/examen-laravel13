<?php

namespace App\Services\LabCatalog;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\LabTest;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Módulo catálogo de pruebas de laboratorio: es el único que escribe en `lab_tests`. Órdenes, recepción, resultados y
 * validación lo leen por LabTestDirectory (DatabaseLabTestDirectory); las órdenes copian los datos de la prueba al
 * pedirla, así que una versión nueva no cambia lo ya pedido.
 */
class LabTestCatalogService
{
    public function __construct(private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    public static function today(?Hospital $hospital): string
    {
        return now($hospital?->timezone ?: config('app.timezone'))->toDateString();
    }

    public static function normalize(?string $text): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim((string) $text)) ?? ''));
    }

    /**
     * CU-04: pruebas vigentes en la fecha, por texto en el código o el nombre, sección y tipo de muestra (RF-05).
     * % y _ se buscan como texto, no como comodines (corrige el defecto D-04).
     *
     * @return array{items: Collection<int, LabTest>, total: int}
     */
    public function search(?string $query, ?string $section, ?string $specimen, string $date): array
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], self::normalize($query)).'%';
        $base = LabTest::query()->validOn($date)
            ->when($query !== null && trim($query) !== '', fn ($q) => $q->whereRaw("search_text LIKE ? ESCAPE '\\'", [$like]))
            ->when($section, fn ($q, $s) => $q->where('section', $s))
            ->when($specimen, fn ($q, $s) => $q->where('specimen_type', $s));

        return ['items' => (clone $base)->orderBy('name')->orderBy('code')->limit(LabTestOptions::MAX_RESULTS)->get(), 'total' => $base->count()];
    }

    /** La versión vigente del código en la fecha (RN-06). */
    public function validOn(string $code, string $date): ?LabTest
    {
        return LabTest::query()->validOn($date)->where('code', mb_strtoupper(trim($code)))->first();
    }

    /**
     * Todas las versiones del código, de la más antigua a la más reciente.
     *
     * @return Collection<int, LabTest>
     */
    public function history(string $code): Collection
    {
        $versions = LabTest::query()->with(['creator', 'closer'])->where('code', mb_strtoupper(trim($code)))->orderBy('version')->get();

        return $versions->isEmpty()
            ? throw new DomainRuleException('PRUEBA_NO_ENCONTRADA', 'La prueba no existe en el catálogo de este hospital.', 404)
            : $versions;
    }

    /**
     * CU-01 a CU-03: alta de una prueba nueva (versión 1) con sus requisitos de muestra y su vigencia.
     *
     * @param  array<string, mixed>  $data  validado por LabTestRequest
     */
    public function register(CentralUser $actor, Hospital $hospital, array $data, ?string $ip): LabTest
    {
        $code = mb_strtoupper(trim((string) $data['code']));
        $this->ensureFromNotPast($hospital, $data['valid_from']);
        $userRef = $this->refs->forUser($actor, $hospital)->local_user_ref_id;

        $test = $this->atomically(function () use ($code, $data, $userRef) {
            if (LabTest::query()->where('code', $code)->lockForUpdate()->exists()) {
                throw new DomainRuleException('CODIGO_EXISTENTE', "{$code} ya está en el catálogo. Para cambiarla, registre una versión nueva.", 409, ['codigo' => $code]);
            }

            return LabTest::query()->create($this->sheet($data, $code) + ['code' => $code, 'version' => 1, 'created_by_user_ref_id' => $userRef, 'created_at' => now()]);
        });

        $this->audit->record('catalogo-pruebas-laboratorio', 'PRUEBA_REGISTRADA', 'exito', $hospital->hospital_id, $actor->user_id, 'prueba:'.$code,
            ['version' => 1, 'nombre' => $test->name, 'vigente_desde' => $test->valid_from->toDateString()], $ip);

        return $test;
    }

    /**
     * CU-03 y RN-05: una versión nueva con la ficha completa. Si la vigente se cruza, se cierra el día previo; la
     * prueba, sus requisitos y el cierre se guardan juntos o no se guarda nada (RN-07).
     *
     * @param  array<string, mixed>  $data  validado por LabTestRequest
     */
    public function newVersion(CentralUser $actor, Hospital $hospital, string $code, array $data, ?string $ip): LabTest
    {
        $this->ensureFromNotPast($hospital, $data['valid_from']);
        $userRef = $this->refs->forUser($actor, $hospital)->local_user_ref_id;

        [$test, $closedOn] = $this->atomically(function () use ($code, $data, $userRef) {
            $current = LabTest::query()->where('code', mb_strtoupper(trim($code)))->orderByDesc('version')->lockForUpdate()->first()
                ?? throw new DomainRuleException('PRUEBA_NO_ENCONTRADA', 'La prueba no existe en el catálogo de este hospital.', 404);
            if (isset($data['version']) && (int) $data['version'] !== $current->version) {
                throw new DomainRuleException('CONFLICTO_CONCURRENTE', "Otra persona registró la versión {$current->version} de {$current->code} mientras usted editaba. Vuelva a cargarla.", 409,
                    ['version_actual' => $current->version]);
            }
            $plan = ValidityPlan::for($current, $data['valid_from']);
            if ($plan->closePreviousOn !== null) {
                $current->forceFill(['valid_to' => $plan->closePreviousOn, 'closed_by_user_ref_id' => $userRef])->save();
            }
            $new = LabTest::query()->create($this->sheet($data, $current->code) + [
                'code' => $current->code, 'version' => $current->version + 1, 'initial_load' => $current->initial_load,
                'created_by_user_ref_id' => $userRef, 'created_at' => now(),
            ]);

            return [$new, $plan->closePreviousOn];
        });

        $this->audit->record('catalogo-pruebas-laboratorio', 'PRUEBA_NUEVA_VERSION', 'exito', $hospital->hospital_id, $actor->user_id, 'prueba:'.$test->code,
            ['version' => $test->version, 'vigente_desde' => $test->valid_from->toDateString(), 'anterior_hasta' => $closedOn], $ip);

        return $test;
    }

    /** RN-04: la fecha desde no puede ser anterior a hoy en el hospital (la hora del navegador no cuenta). */
    private function ensureFromNotPast(Hospital $hospital, string $from): void
    {
        if ($from < self::today($hospital)) {
            throw ValidationException::withMessages(['valid_from' => 'La fecha desde no puede ser anterior a hoy.']);
        }
    }

    /**
     * RN-07: todo o nada. Una regla rechazada es una respuesta normal; una falla de la base es un 500 sin guardar nada,
     * con el detalle solo en el registro del servidor.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function atomically(callable $work): mixed
    {
        try {
            return DB::connection('hospital')->transaction($work);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'lab_tests_version_overlap')) {
                throw new DomainRuleException('VIGENCIA_SUPERPUESTA', 'Las vigencias de dos versiones se cruzan.', 409);
            }
            if (str_contains($e->getMessage(), 'lab_tests_code_version_unique')) {
                throw new DomainRuleException('CONFLICTO_CONCURRENTE', 'Otra persona registró esta prueba a la vez. Vuelva a cargarla.', 409);
            }
            Log::error('catalogo-lab.persistencia', ['request_id' => request()->attributes->get('request_id'), 'error' => $e->getMessage()]);
            throw new DomainRuleException('ERROR_PERSISTENCIA', 'No se pudo guardar la prueba y no se guardó nada. Intente de nuevo.', 500);
        }
    }

    /**
     * La ficha normalizada como se guarda.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sheet(array $data, string $code): array
    {
        $text = fn ($v) => ($v = preg_replace('/\s+/u', ' ', trim((string) $v))) === '' ? null : $v;
        $number = fn ($v) => $v === null || $v === '' ? null : (string) $v;
        $name = $text($data['name']);

        return [
            'name' => $name, 'section' => $data['section'], 'turnaround_hours' => (int) $data['turnaround_hours'],
            'specimen_type' => $data['specimen_type'], 'container' => $text($data['container']), 'min_volume_ml' => (string) $data['min_volume_ml'],
            'fasting_hours' => (int) $data['fasting_hours'], 'preparation_instructions' => $text($data['preparation_instructions'] ?? null),
            'storage' => $data['storage'], 'stability_hours' => (int) $data['stability_hours'], 'unit' => $text($data['unit']),
            'ref_low' => $number($data['ref_low'] ?? null), 'ref_high' => $number($data['ref_high'] ?? null),
            'critical_low' => $number($data['critical_low'] ?? null), 'critical_high' => $number($data['critical_high'] ?? null),
            'valid_from' => $data['valid_from'], 'valid_to' => $data['valid_to'] ?? null,
            'search_text' => self::normalize($code.' '.$name.' '.LabTestOptions::SECTIONS[$data['section']]),
        ];
    }
}
