<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, módulo catálogo de medicamentos. Una fila por versión: el código MED-NNNNNN no cambia entre
// versiones (RN-06) y la vigencia es [valid_from, valid_to). La base repite las reglas del dominio (RNF-04):
//  - RN-01: dos versiones del mismo código nunca se cruzan (medications_version_overlap);
//  - RN-04: dos códigos con la misma clave de equivalencia nunca están vigentes a la vez (medications_equivalent_overlap);
//  - RN-05: cada columna tiene los mismos límites que valida la API;
//  - RN-02: no se borra un código que aparece en una receta, y una versión cerrada no se reabre ni se edita.
// legacy_code es el código del catálogo provisional (p. ej. AMOX500), para que las recetas anteriores sigan resolviendo.
// created_by_user_ref_id vacío = catálogo inicial que carga la instalación, antes de que el hospital tenga usuarios.
return new class extends Migration
{
    protected $connection = 'hospital';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            CREATE TABLE medications (
                medication_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                code text NOT NULL CONSTRAINT medications_code_check CHECK (code ~ '^MED-[0-9]{6}$'),
                version smallint NOT NULL CONSTRAINT medications_version_check CHECK (version BETWEEN 1 AND 999),
                name varchar(150) NOT NULL CONSTRAINT medications_name_check CHECK (char_length(name) >= 3 AND name = btrim(name)),
                active_ingredient varchar(120) NOT NULL
                    CONSTRAINT medications_active_ingredient_check CHECK (char_length(active_ingredient) >= 3 AND active_ingredient = btrim(active_ingredient)),
                strength_value numeric(9,3) NOT NULL CONSTRAINT medications_strength_value_check CHECK (strength_value > 0),
                strength_unit text NOT NULL
                    CONSTRAINT medications_strength_unit_check CHECK (strength_unit IN ('mg', 'g', 'mcg', 'UI', 'MUI', 'mL', '%', 'mg/mL', 'mg/5mL')),
                dosage_form text NOT NULL CONSTRAINT medications_dosage_form_check CHECK (dosage_form IN ('TABLETA', 'CAPSULA', 'JARABE', 'SUSPENSION',
                    'SOLUCION_ORAL', 'SOLUCION_INYECTABLE', 'POLVO_INYECTABLE', 'CREMA', 'UNGUENTO', 'GOTAS_OFTALMICAS', 'INHALADOR', 'SUPOSITORIO')),
                route text NOT NULL CONSTRAINT medications_route_check CHECK (route IN ('ORAL', 'INTRAVENOSA', 'INTRAMUSCULAR', 'SUBCUTANEA', 'TOPICA',
                    'OFTALMICA', 'INHALATORIA', 'RECTAL')),
                presentation varchar(120) NOT NULL
                    CONSTRAINT medications_presentation_check CHECK (char_length(presentation) >= 3 AND presentation = btrim(presentation)),
                sanitary_registration text CONSTRAINT medications_sanitary_registration_check CHECK (sanitary_registration ~ '^[A-Z0-9-]{4,30}$'),
                therapeutic_group text NOT NULL CONSTRAINT medications_therapeutic_group_check CHECK (therapeutic_group IN ('ANTIBIOTICO', 'ANALGESICO',
                    'CARDIOVASCULAR', 'GASTROINTESTINAL', 'ENDOCRINO', 'RESPIRATORIO', 'SOLUCIONES', 'OTRO')),
                allergy_groups jsonb NOT NULL DEFAULT '[]' CONSTRAINT medications_allergy_groups_check CHECK (jsonb_typeof(allergy_groups) = 'array'),
                legacy_code text CONSTRAINT medications_legacy_code_check CHECK (legacy_code ~ '^[A-Z0-9]{2,20}$'),
                equivalence_key text NOT NULL CONSTRAINT medications_equivalence_key_check CHECK (btrim(equivalence_key) <> ''),
                search_text text NOT NULL,
                valid_from date NOT NULL,
                valid_to date,
                close_reason varchar(200),
                created_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                closed_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                created_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT medications_validity_check CHECK (valid_from BETWEEN DATE '2000-01-01' AND DATE '2099-12-31'
                    AND (valid_to IS NULL OR (valid_to > valid_from AND valid_to <= DATE '2099-12-31'))),
                CONSTRAINT medications_close_reason_check CHECK (close_reason IS NULL OR (valid_to IS NOT NULL AND char_length(btrim(close_reason)) >= 5)),
                CONSTRAINT medications_code_version_unique UNIQUE (code, version),
                CONSTRAINT medications_version_overlap EXCLUDE USING gist (code WITH =, daterange(valid_from, valid_to, '[)') WITH &&),
                CONSTRAINT medications_equivalent_overlap EXCLUDE USING gist (equivalence_key WITH =, code WITH <>, daterange(valid_from, valid_to, '[)') WITH &&)
            );
            CREATE INDEX medications_created_by_index ON medications (created_by_user_ref_id);
            CREATE INDEX medications_closed_by_index ON medications (closed_by_user_ref_id);
            CREATE INDEX medications_legacy_code_index ON medications (legacy_code) WHERE legacy_code IS NOT NULL;
            CREATE INDEX medications_validity_index ON medications (valid_from, valid_to);

            -- Una versión solo cambia para cerrarse: nada más se edita y una versión cerrada no se reabre.
            -- Un código que aparece en una receta no se borra: se da de baja.
            CREATE OR REPLACE FUNCTION preserve_medication_version() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF EXISTS (SELECT 1 FROM prescriptions WHERE medication_code IN (OLD.code, OLD.legacy_code)) THEN
                        RAISE EXCEPTION 'medications_in_use' USING ERRCODE = '23503';
                    END IF;
                    RETURN OLD;
                END IF;
                IF OLD.valid_to IS NOT NULL OR NEW.valid_to IS NULL
                    OR (to_jsonb(NEW) - ARRAY['valid_to', 'close_reason', 'closed_by_user_ref_id'])
                        IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['valid_to', 'close_reason', 'closed_by_user_ref_id']) THEN
                    RAISE EXCEPTION 'medications_version_immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END; $$;
            CREATE TRIGGER medications_version_retention BEFORE UPDATE OR DELETE ON medications
                FOR EACH ROW EXECUTE FUNCTION preserve_medication_version();
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            DROP TRIGGER medications_version_retention ON medications;
            DROP FUNCTION preserve_medication_version();
            DROP TABLE medications;
            SQL);
    }
};
