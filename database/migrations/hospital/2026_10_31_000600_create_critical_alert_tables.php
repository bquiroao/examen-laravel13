<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, módulo alertas críticas y notificaciones internas. Ciclo CREADA → ENVIADA → (ESCALADA) → ACUSADA → CERRADA.
// La base repite las reglas del dominio:
//  - RN-02: un mismo origen + id de origen crea una sola alerta (critical_alerts_source_unique);
//  - RN-05: el nivel de escalamiento va de 0 a 2;
//  - RN-08: hasta 3 intentos de entrega por destinatario;
//  - RN-09: solo se cierra una alerta acusada, con nota;
//  - RN-10: el historial solo crece (no se edita ni se borra);
//  - RN-11: el plazo de acuse por prioridad va de 5 a 60 minutos.
// La alerta guarda solo el código del paciente, nunca su nombre (RNF-02).
return new class extends Migration
{
    protected $connection = 'hospital';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            CREATE TABLE critical_alert_policies (
                priority text PRIMARY KEY CONSTRAINT critical_alert_policies_priority_check CHECK (priority IN ('CRITICA', 'ALTA')),
                ack_minutes integer NOT NULL CONSTRAINT critical_alert_policies_minutes_check CHECK (ack_minutes BETWEEN 5 AND 60),
                updated_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                updated_at timestamptz NOT NULL DEFAULT now()
            );
            CREATE INDEX critical_alert_policies_updated_by_index ON critical_alert_policies (updated_by_user_ref_id);
            INSERT INTO critical_alert_policies (priority, ack_minutes) VALUES ('CRITICA', 15), ('ALTA', 30);

            CREATE TABLE critical_alerts (
                critical_alert_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                local_patient_id uuid NOT NULL REFERENCES local_patients(local_patient_id) ON DELETE RESTRICT,
                patient_code text NOT NULL CONSTRAINT critical_alerts_patient_code_check CHECK (btrim(patient_code) <> ''),
                care_episode_id uuid REFERENCES care_episodes(care_episode_id) ON DELETE RESTRICT,
                source text NOT NULL CONSTRAINT critical_alerts_source_check CHECK (source IN ('laboratorio', 'signos_vitales')),
                source_id text NOT NULL CONSTRAINT critical_alerts_source_id_check CHECK (btrim(source_id) <> ''),
                priority text NOT NULL CONSTRAINT critical_alerts_priority_check CHECK (priority IN ('CRITICA', 'ALTA')),
                title varchar(150) NOT NULL CONSTRAINT critical_alerts_title_check CHECK (btrim(title) <> ''),
                description varchar(500) NOT NULL CONSTRAINT critical_alerts_description_check CHECK (btrim(description) <> ''),
                status text NOT NULL DEFAULT 'CREADA' CONSTRAINT critical_alerts_status_check CHECK (status IN ('CREADA', 'ENVIADA', 'ESCALADA', 'ACUSADA', 'CERRADA')),
                level smallint NOT NULL DEFAULT 0 CONSTRAINT critical_alerts_level_check CHECK (level BETWEEN 0 AND 2),
                deadline_at timestamptz,
                escalation_exhausted boolean NOT NULL DEFAULT false,
                acknowledged_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                acknowledged_at timestamptz,
                acknowledged_level smallint,
                closed_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                closed_at timestamptz,
                close_note varchar(500),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT critical_alerts_source_unique UNIQUE (source, source_id),
                CONSTRAINT critical_alerts_ack_check CHECK ((status IN ('ACUSADA', 'CERRADA')) = (acknowledged_at IS NOT NULL AND acknowledged_by_user_ref_id IS NOT NULL)),
                CONSTRAINT critical_alerts_close_check CHECK ((status = 'CERRADA') = (closed_at IS NOT NULL AND char_length(btrim(coalesce(close_note, ''))) >= 5))
            );
            CREATE INDEX critical_alerts_local_patient_id_index ON critical_alerts (local_patient_id);
            CREATE INDEX critical_alerts_care_episode_id_index ON critical_alerts (care_episode_id);
            CREATE INDEX critical_alerts_acknowledged_by_index ON critical_alerts (acknowledged_by_user_ref_id);
            CREATE INDEX critical_alerts_closed_by_index ON critical_alerts (closed_by_user_ref_id);
            CREATE INDEX critical_alerts_due_index ON critical_alerts (deadline_at) WHERE status IN ('ENVIADA', 'ESCALADA');

            CREATE TABLE critical_alert_notifications (
                notification_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                critical_alert_id uuid NOT NULL REFERENCES critical_alerts(critical_alert_id) ON DELETE RESTRICT,
                recipient_user_ref_id uuid NOT NULL REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                level smallint NOT NULL CONSTRAINT critical_alert_notifications_level_check CHECK (level BETWEEN 0 AND 2),
                attempts smallint NOT NULL CONSTRAINT critical_alert_notifications_attempts_check CHECK (attempts BETWEEN 1 AND 3),
                delivered boolean NOT NULL,
                last_attempt_at timestamptz NOT NULL,
                read_at timestamptz,
                CONSTRAINT critical_alert_notifications_unique UNIQUE (critical_alert_id, recipient_user_ref_id)
            );
            CREATE INDEX critical_alert_notifications_recipient_index ON critical_alert_notifications (recipient_user_ref_id);

            CREATE TABLE critical_alert_events (
                critical_alert_event_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                critical_alert_id uuid NOT NULL REFERENCES critical_alerts(critical_alert_id) ON DELETE RESTRICT,
                event text NOT NULL CONSTRAINT critical_alert_events_event_check CHECK (event IN ('creada', 'enviada', 'sin_destinatarios', 'entrega_fallida',
                    'escalada', 'escalamiento_agotado', 'acusada', 'cerrada')),
                level smallint NOT NULL CONSTRAINT critical_alert_events_level_check CHECK (level BETWEEN 0 AND 2),
                actor text NOT NULL CONSTRAINT critical_alert_events_actor_check CHECK (btrim(actor) <> ''),
                actor_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                detail jsonb NOT NULL DEFAULT '{}',
                occurred_at timestamptz NOT NULL
            );
            CREATE INDEX critical_alert_events_alert_index ON critical_alert_events (critical_alert_id, occurred_at);
            CREATE INDEX critical_alert_events_actor_index ON critical_alert_events (actor_user_ref_id);
            -- Un solo acuse por alerta (RN-06), aunque dos personas acusen a la vez.
            CREATE UNIQUE INDEX critical_alert_events_one_ack ON critical_alert_events (critical_alert_id) WHERE event = 'acusada';

            CREATE OR REPLACE FUNCTION preserve_critical_alert_event() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'critical_alert_events_append_only';
            END; $$;
            CREATE TRIGGER critical_alert_events_append_only BEFORE UPDATE OR DELETE ON critical_alert_events
                FOR EACH ROW EXECUTE FUNCTION preserve_critical_alert_event();
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            DROP TRIGGER critical_alert_events_append_only ON critical_alert_events;
            DROP FUNCTION preserve_critical_alert_event();
            DROP TABLE critical_alert_events;
            DROP TABLE critical_alert_notifications;
            DROP TABLE critical_alerts;
            DROP TABLE critical_alert_policies;
            SQL);
    }
};
