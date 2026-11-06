<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, módulo catálogo de pruebas de laboratorio. Una fila por versión de la prueba, con sus requisitos de
// muestra y su vigencia [valid_from, valid_to]: valid_to es el ÚLTIMO día vigente (RN-04). La base repite las reglas:
//  - RN-05: dos versiones del mismo código nunca se cruzan (lab_tests_version_overlap);
//  - RN-02, RN-03 y los defectos D-02 y D-03: cada columna tiene los mismos límites que valida la API;
//  - RN-08: una versión no se edita ni se borra (las órdenes pasadas la referencian); solo se adelanta su cierre.
// Los valores de referencia y críticos van aquí porque ingreso y validación de resultados ya los usan.
// initial_load = catálogo inicial que carga la instalación: conserva el código provisional (HB, GLU…) que ya usan
// las órdenes y los resultados. Las pruebas que se registran después usan el formato AAA-000.
return new class extends Migration
{
    protected $connection = 'hospital';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            CREATE TABLE lab_tests (
                lab_test_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                code text NOT NULL,
                version smallint NOT NULL CONSTRAINT lab_tests_version_check CHECK (version BETWEEN 1 AND 999),
                name varchar(150) NOT NULL CONSTRAINT lab_tests_name_check CHECK (char_length(name) >= 2 AND name = btrim(name)),
                section text NOT NULL CONSTRAINT lab_tests_section_check CHECK (section IN ('hematologia', 'quimica-clinica', 'microbiologia',
                    'inmunologia', 'uroanalisis', 'coprologia')),
                turnaround_hours integer NOT NULL CONSTRAINT lab_tests_turnaround_check CHECK (turnaround_hours BETWEEN 1 AND 720),
                specimen_type text NOT NULL CONSTRAINT lab_tests_specimen_type_check CHECK (specimen_type IN ('sangre-total', 'suero', 'plasma',
                    'orina', 'heces', 'hisopado')),
                container varchar(80) NOT NULL CONSTRAINT lab_tests_container_check CHECK (char_length(container) >= 2 AND container = btrim(container)),
                min_volume_ml numeric(6,2) NOT NULL CONSTRAINT lab_tests_min_volume_check CHECK (min_volume_ml > 0),
                fasting_hours integer NOT NULL CONSTRAINT lab_tests_fasting_check CHECK (fasting_hours BETWEEN 0 AND 72),
                preparation_instructions varchar(300),
                storage text NOT NULL CONSTRAINT lab_tests_storage_check CHECK (storage IN ('ambiente', 'refrigerada', 'congelada')),
                stability_hours integer NOT NULL CONSTRAINT lab_tests_stability_check CHECK (stability_hours BETWEEN 1 AND 8760),
                unit varchar(20) NOT NULL CONSTRAINT lab_tests_unit_check CHECK (btrim(unit) <> ''),
                ref_low numeric(12,4), ref_high numeric(12,4), critical_low numeric(12,4), critical_high numeric(12,4),
                search_text text NOT NULL,
                initial_load boolean NOT NULL DEFAULT false,
                valid_from date NOT NULL,
                valid_to date,
                created_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                closed_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                created_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT lab_tests_code_check CHECK (code ~ '^[A-Z]{3}-[0-9]{3}$' OR (initial_load AND code ~ '^[A-Z0-9]{1,10}$')),
                CONSTRAINT lab_tests_author_check CHECK (initial_load OR created_by_user_ref_id IS NOT NULL),
                CONSTRAINT lab_tests_ranges_check CHECK ((ref_low IS NULL OR ref_high IS NULL OR ref_low <= ref_high)
                    AND (critical_low IS NULL OR ref_low IS NULL OR critical_low < ref_low)
                    AND (critical_high IS NULL OR ref_high IS NULL OR critical_high > ref_high)
                    AND (critical_low IS NULL OR critical_high IS NULL OR critical_low < critical_high)),
                CONSTRAINT lab_tests_validity_check CHECK (valid_from BETWEEN DATE '2000-01-01' AND DATE '2099-12-31'
                    AND (valid_to IS NULL OR (valid_to >= valid_from AND valid_to <= DATE '2099-12-31'))),
                CONSTRAINT lab_tests_code_version_unique UNIQUE (code, version),
                CONSTRAINT lab_tests_version_overlap EXCLUDE USING gist (code WITH =, daterange(valid_from, valid_to, '[]') WITH &&)
            );
            CREATE INDEX lab_tests_created_by_index ON lab_tests (created_by_user_ref_id);
            CREATE INDEX lab_tests_closed_by_index ON lab_tests (closed_by_user_ref_id);
            CREATE INDEX lab_tests_validity_index ON lab_tests (valid_from, valid_to);

            -- Una versión no se borra ni se edita: solo se puede adelantar su cierre (al registrar la versión siguiente).
            CREATE OR REPLACE FUNCTION preserve_lab_test_version() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'lab_tests_version_immutable' USING ERRCODE = '23514';
                END IF;
                IF NEW.valid_to IS NULL OR (OLD.valid_to IS NOT NULL AND NEW.valid_to > OLD.valid_to)
                    OR (to_jsonb(NEW) - ARRAY['valid_to', 'closed_by_user_ref_id']) IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['valid_to', 'closed_by_user_ref_id']) THEN
                    RAISE EXCEPTION 'lab_tests_version_immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END; $$;
            CREATE TRIGGER lab_tests_version_retention BEFORE UPDATE OR DELETE ON lab_tests
                FOR EACH ROW EXECUTE FUNCTION preserve_lab_test_version();
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            DROP TRIGGER lab_tests_version_retention ON lab_tests;
            DROP FUNCTION preserve_lab_test_version();
            DROP TABLE lab_tests;
            SQL);
    }
};
