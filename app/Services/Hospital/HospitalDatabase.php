<?php

namespace App\Services\Hospital;

use App\Models\Central\Hospital;
use Illuminate\Support\Facades\DB;

/**
 * Esquema federado: cada hospital tiene su propia base HOSPITAL. La conexión «hospital» apunta a la base
 * del hospital de la petición (metadata.database en CENTRAL; si no hay, la base configurada en .env).
 */
class HospitalDatabase
{
    private ?string $default = null;

    public function use(Hospital $hospital): void
    {
        $this->switchTo($this->databaseFor($hospital));
    }

    public function databaseFor(Hospital $hospital): string
    {
        $this->default ??= (string) config('database.connections.hospital.database');
        $name = $hospital->metadata['database'] ?? null;

        return is_string($name) && preg_match('/^[a-z][a-z0-9_]{0,62}$/', $name) ? $name : $this->default;
    }

    public function switchTo(string $database): void
    {
        $this->default ??= (string) config('database.connections.hospital.database');
        if (config('database.connections.hospital.database') === $database) {
            return;
        }
        DB::purge('hospital');
        config(['database.connections.hospital.database' => $database]);
    }

    public function reset(): void
    {
        if ($this->default !== null) {
            $this->switchTo($this->default);
        }
    }
}
