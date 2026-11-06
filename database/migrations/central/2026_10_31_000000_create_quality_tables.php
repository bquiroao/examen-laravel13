<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, fase 6. Calidad de la plataforma (nivel red, en CENTRAL):
//  - qa_defects (módulo qa-regresion-defectos): defectos con severidad y ciclo de vida; los cierra QA volviendo a probar.
//  - qa_runs (módulo qa-e2e-ci-despliegue): ejecuciones de pruebas por versión, base del quality gate.
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            CREATE TABLE qa_defects (
                defect_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                code text NOT NULL,
                title text NOT NULL CONSTRAINT qa_defects_title_check CHECK (btrim(title) <> ''),
                module text NOT NULL,
                severity text NOT NULL CONSTRAINT qa_defects_severity_check CHECK (severity IN ('critica', 'alta', 'media', 'baja')),
                status text NOT NULL DEFAULT 'abierto' CONSTRAINT qa_defects_status_check CHECK (status IN ('abierto', 'en_correccion', 'corregido', 'cerrado', 'reabierto')),
                steps text NOT NULL CONSTRAINT qa_defects_steps_check CHECK (btrim(steps) <> ''),
                version text,
                reported_by uuid NOT NULL,
                resolution_note text,
                closed_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT qa_defects_code_unique UNIQUE (code),
                CONSTRAINT qa_defects_closure_check CHECK ((status = 'cerrado') = (closed_at IS NOT NULL))
            );
            CREATE INDEX qa_defects_status_index ON qa_defects (status, severity);

            CREATE TABLE qa_runs (
                run_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                version text NOT NULL CONSTRAINT qa_runs_version_check CHECK (btrim(version) <> ''),
                suite text NOT NULL CONSTRAINT qa_runs_suite_check CHECK (btrim(suite) <> ''),
                total integer NOT NULL CONSTRAINT qa_runs_total_check CHECK (total > 0),
                passed integer NOT NULL,
                failed integer NOT NULL,
                flaky integer NOT NULL DEFAULT 0,
                source text NOT NULL DEFAULT 'manual' CONSTRAINT qa_runs_source_check CHECK (source IN ('manual', 'ci')),
                commit_sha text,
                registered_by uuid,
                ran_at timestamptz NOT NULL DEFAULT now(),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT qa_runs_counts_check CHECK (passed >= 0 AND failed >= 0 AND flaky >= 0 AND passed + failed = total AND flaky <= passed)
            );
            CREATE INDEX qa_runs_version_index ON qa_runs (version, ran_at DESC);
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared('DROP TABLE qa_runs; DROP TABLE qa_defects;');
    }
};
