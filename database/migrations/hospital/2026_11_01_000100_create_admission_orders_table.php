<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, módulo admisión hospitalaria y asignación de cama (mejora): la orden de ingreso pendiente de cama.
// El médico ordena el ingreso y el paciente espera; al asignarle una cama se crea el internamiento como siempre
// (care_episodes + bed_stays) y la orden queda «asignada». Así se mide el tiempo de espera de cama, sin crear
// internamientos sin cama que afectarían a ocupación, traslados, altas y signos vitales.
//  - una sola orden pendiente por paciente (admission_orders_one_pending);
//  - cada estado lleva sus datos: asignada = internamiento y hora; cancelada = motivo y hora.
return new class extends Migration
{
    protected $connection = 'hospital';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            CREATE TABLE admission_orders (
                admission_order_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                local_patient_id uuid NOT NULL REFERENCES local_patients(local_patient_id) ON DELETE RESTRICT,
                ordered_by_user_ref_id uuid NOT NULL REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                bed_type text NOT NULL CONSTRAINT admission_orders_bed_type_check CHECK (bed_type IN ('standard', 'uci', 'aislamiento', 'pediatrica')),
                isolation boolean NOT NULL DEFAULT false,
                source text NOT NULL CONSTRAINT admission_orders_source_check CHECK (source IN ('emergencia', 'consulta_externa', 'referencia')),
                priority text NOT NULL CONSTRAINT admission_orders_priority_check CHECK (priority IN ('urgente', 'programada')),
                reason varchar(500) NOT NULL CONSTRAINT admission_orders_reason_check CHECK (char_length(btrim(reason)) >= 5),
                status text NOT NULL DEFAULT 'pendiente' CONSTRAINT admission_orders_status_check CHECK (status IN ('pendiente', 'asignada', 'cancelada')),
                ordered_at timestamptz NOT NULL,
                care_episode_id uuid REFERENCES care_episodes(care_episode_id) ON DELETE RESTRICT,
                assigned_at timestamptz,
                assigned_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                cancelled_at timestamptz,
                cancelled_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                cancel_reason varchar(300),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT admission_orders_assigned_check CHECK ((status = 'asignada') = (care_episode_id IS NOT NULL AND assigned_at IS NOT NULL AND assigned_by_user_ref_id IS NOT NULL)),
                CONSTRAINT admission_orders_assigned_after_check CHECK (assigned_at IS NULL OR assigned_at >= ordered_at),
                CONSTRAINT admission_orders_cancelled_check CHECK ((status = 'cancelada') = (cancelled_at IS NOT NULL AND char_length(btrim(coalesce(cancel_reason, ''))) >= 5))
            );
            CREATE INDEX admission_orders_local_patient_id_index ON admission_orders (local_patient_id);
            CREATE INDEX admission_orders_ordered_by_index ON admission_orders (ordered_by_user_ref_id);
            CREATE INDEX admission_orders_care_episode_id_index ON admission_orders (care_episode_id);
            CREATE INDEX admission_orders_assigned_by_index ON admission_orders (assigned_by_user_ref_id);
            CREATE INDEX admission_orders_cancelled_by_index ON admission_orders (cancelled_by_user_ref_id);
            CREATE UNIQUE INDEX admission_orders_one_pending ON admission_orders (local_patient_id) WHERE status = 'pendiente';
            CREATE UNIQUE INDEX admission_orders_one_per_episode ON admission_orders (care_episode_id) WHERE care_episode_id IS NOT NULL;
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared('DROP TABLE admission_orders;');
    }
};
