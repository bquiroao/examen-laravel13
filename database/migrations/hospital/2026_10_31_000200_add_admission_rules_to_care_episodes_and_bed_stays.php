<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, módulo admisión hospitalaria y asignación de cama.
// - Origen de la admisión (emergencia, consulta externa o referencia).
// - Defecto AU-01: un episodio «closed» o «cancelled» sin ended_at pasaba el CHECK, porque la comparación con NULL
//   no es falsa. Ahora se exige la fecha de fin.
// - Defecto AU-05: una cama con estancia abierta seguía «available». Al abrir una estancia, la cama queda «occupied».
return new class extends Migration
{
    protected $connection = 'hospital';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            ALTER TABLE care_episodes
                ADD COLUMN admission_source text CONSTRAINT care_episodes_admission_source_check
                    CHECK (admission_source IN ('emergencia', 'consulta_externa', 'referencia')),
                DROP CONSTRAINT care_episodes_status_period_check,
                ADD CONSTRAINT care_episodes_status_period_check
                    CHECK ((status = 'open' AND ended_at IS NULL) OR (status IN ('closed', 'cancelled') AND ended_at IS NOT NULL AND ended_at > started_at));

            CREATE OR REPLACE FUNCTION mark_bed_occupied() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE beds SET status = 'occupied', updated_at = now() WHERE bed_id = NEW.bed_id AND status <> 'occupied';
                RETURN NEW;
            END; $$;
            CREATE TRIGGER bed_stays_mark_bed_occupied AFTER INSERT ON bed_stays FOR EACH ROW WHEN (NEW.ended_at IS NULL) EXECUTE FUNCTION mark_bed_occupied();
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            DROP TRIGGER bed_stays_mark_bed_occupied ON bed_stays;
            DROP FUNCTION mark_bed_occupied();
            ALTER TABLE care_episodes
                DROP CONSTRAINT care_episodes_status_period_check,
                ADD CONSTRAINT care_episodes_status_period_check
                    CHECK ((status = 'open' AND ended_at IS NULL) OR (status IN ('closed', 'cancelled') AND ended_at > started_at)),
                DROP COLUMN admission_source;
            SQL);
    }
};
