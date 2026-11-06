<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, fase 3. Catálogo de salas y camas (capacidad de la sala, motivo del último cambio de estado de
// la cama) y traslados y altas (tipo y motivo del alta del episodio).
return new class extends Migration
{
    protected $connection = 'hospital';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            ALTER TABLE wards
                ADD COLUMN capacity integer CONSTRAINT wards_capacity_check CHECK (capacity IS NULL OR capacity > 0),
                ADD CONSTRAINT wards_code_name_nonblank_check CHECK (btrim(code) <> '' AND btrim(name) <> '');

            ALTER TABLE beds
                ADD COLUMN status_reason text,
                ADD COLUMN status_changed_at timestamptz,
                ADD CONSTRAINT beds_code_nonblank_check CHECK (btrim(code) <> ''),
                ADD CONSTRAINT beds_type_check CHECK (type IN ('standard', 'uci', 'aislamiento', 'pediatrica'));

            ALTER TABLE care_episodes
                ADD COLUMN discharge_type text CONSTRAINT care_episodes_discharge_type_check
                    CHECK (discharge_type IN ('medica', 'voluntaria', 'referencia', 'defuncion')),
                ADD COLUMN discharge_reason text;
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            ALTER TABLE care_episodes DROP COLUMN discharge_reason, DROP COLUMN discharge_type;
            ALTER TABLE beds
                DROP CONSTRAINT beds_type_check,
                DROP CONSTRAINT beds_code_nonblank_check,
                DROP COLUMN status_changed_at,
                DROP COLUMN status_reason;
            ALTER TABLE wards DROP CONSTRAINT wards_code_name_nonblank_check, DROP COLUMN capacity;
            SQL);
    }
};
