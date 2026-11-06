<?php

namespace App\Services\Audit;

use App\Models\Central\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bitácora encadenada (módulo gobernanza-auditoría, R-03): cada sello depende del anterior del mismo hospital,
 * así que alterar una fila rompe esa y todas las siguientes. La tabla además es de solo inserción.
 */
class AuditTrail
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public const EMISOR = 'nido-api';

    /** @param array<string, mixed> $detalle */
    public function record(string $modulo, string $accion, string $resultado, ?string $hospitalId, ?string $usuarioId,
        ?string $recurso = null, array $detalle = [], ?string $ip = null, ?string $correlationId = null): AuditLog
    {
        return DB::connection('central')->transaction(function () use ($modulo, $accion, $resultado, $hospitalId, $usuarioId, $recurso, $detalle, $ip, $correlationId): AuditLog {
            $cadena = $hospitalId ?? '*';
            // Un candado por cadena: dos registros simultáneos del mismo hospital no toman la misma secuencia.
            DB::connection('central')->select('SELECT pg_advisory_xact_lock(hashtext(?))', ['audit_logs:'.$cadena]);

            $ultimo = AuditLog::query()
                ->when($hospitalId === null, fn ($q) => $q->whereNull('hospital_id'), fn ($q) => $q->where('hospital_id', $hospitalId))
                ->orderByDesc('secuencia')->first(['secuencia', 'sello']);

            $fila = [
                'emisor' => self::EMISOR,
                'event_id' => (string) Str::uuid(),
                'hospital_id' => $hospitalId,
                'usuario_id' => $usuarioId,
                'modulo' => $modulo,
                'accion' => $accion,
                'ocurrido_en' => CarbonImmutable::now('UTC')->startOfSecond(),
                'resultado' => $resultado,
                'recurso' => $recurso,
                'correlation_id' => $correlationId,
                'ip' => $ip,
                'detalle' => $detalle,
                'secuencia' => ($ultimo?->secuencia ?? 0) + 1,
                'corrige_a' => null,
                'motivo_correccion' => null,
            ];
            $fila['sello'] = self::seal($ultimo?->sello ?? self::GENESIS, $fila['secuencia'], $fila);

            return AuditLog::query()->create($fila);
        });
    }

    /**
     * Recalcula la cadena de un hospital (null = nivel red).
     *
     * @return array{integra: bool, revisados: int, primera_falla: int|null}
     */
    public function verify(?string $hospitalId): array
    {
        $anterior = self::GENESIS;
        $revisados = 0;
        $query = AuditLog::query()
            ->when($hospitalId === null, fn ($q) => $q->whereNull('hospital_id'), fn ($q) => $q->where('hospital_id', $hospitalId))
            ->orderBy('secuencia');

        foreach ($query->lazy(500) as $log) {
            $revisados++;
            $fila = $log->getAttributes();
            $fila['detalle'] = $log->detalle;
            $fila['ocurrido_en'] = $log->ocurrido_en;
            if ((int) $log->secuencia !== $revisados || ! hash_equals(self::seal($anterior, $revisados, $fila), (string) $log->sello)) {
                return ['integra' => false, 'revisados' => $revisados, 'primera_falla' => (int) $log->id];
            }
            $anterior = (string) $log->sello;
        }

        return ['integra' => true, 'revisados' => $revisados, 'primera_falla' => null];
    }

    /** @param array<string, mixed> $fila */
    public static function seal(string $selloAnterior, int $secuencia, array $fila): string
    {
        $contenido = [
            'emisor' => $fila['emisor'], 'event_id' => $fila['event_id'], 'hospital' => $fila['hospital_id'], 'usuario' => $fila['usuario_id'],
            'modulo' => $fila['modulo'], 'accion' => $fila['accion'],
            'ocurrido_en' => CarbonImmutable::parse($fila['ocurrido_en'])->utc()->format('Y-m-d\TH:i:s\Z'),
            'resultado' => $fila['resultado'], 'recurso' => $fila['recurso'], 'correlation_id' => $fila['correlation_id'],
            'detalle' => self::canonical($fila['detalle']), 'corrige_a' => $fila['corrige_a'], 'motivo_correccion' => $fila['motivo_correccion'],
        ];

        return hash('sha256', $selloAnterior.$secuencia.json_encode($contenido, JSON_UNESCAPED_UNICODE));
    }

    /** jsonb reordena las llaves: se ordenan antes de sellar para que la verificación dé lo mismo. */
    private static function canonical(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }
        if (! array_is_list($valor)) {
            ksort($valor);
        }

        return array_map(self::canonical(...), $valor);
    }
}
