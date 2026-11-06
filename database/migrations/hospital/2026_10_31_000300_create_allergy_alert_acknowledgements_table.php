<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, módulo alergias clínicas y alerta visual en el expediente (RN-09).
// Quién reconoció qué alerta: una alerta ALTA o CRÍTICA se reconoce por usuario y por huella. Si cambian las alergias
// activas, la huella cambia y se vuelve a pedir. Es evidencia clínica: no se modifica ni se borra.
return new class extends Migration
{
    protected $connection = 'hospital';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            CREATE TABLE allergy_alert_acknowledgements (
                acknowledgement_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                local_patient_id uuid NOT NULL REFERENCES local_patients(local_patient_id) ON DELETE RESTRICT,
                acknowledged_by_user_ref_id uuid NOT NULL REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                alert_level text NOT NULL CONSTRAINT allergy_alert_ack_level_check CHECK (alert_level IN ('ALTA', 'CRITICA')),
                alert_fingerprint text NOT NULL CONSTRAINT allergy_alert_ack_fingerprint_check CHECK (alert_fingerprint ~ '^[0-9a-f]{16}$'),
                acknowledged_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT allergy_alert_ack_unique UNIQUE (local_patient_id, acknowledged_by_user_ref_id, alert_fingerprint)
            );
            CREATE INDEX allergy_alert_ack_user_index ON allergy_alert_acknowledgements (acknowledged_by_user_ref_id);

            CREATE OR REPLACE FUNCTION preserve_allergy_alert_acknowledgement() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'allergy_alert_acknowledgements_immutable';
            END; $$;
            CREATE TRIGGER allergy_alert_acknowledgements_retention BEFORE UPDATE OR DELETE ON allergy_alert_acknowledgements
                FOR EACH ROW EXECUTE FUNCTION preserve_allergy_alert_acknowledgement();
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            DROP TRIGGER allergy_alert_acknowledgements_retention ON allergy_alert_acknowledgements;
            DROP FUNCTION preserve_allergy_alert_acknowledgement();
            DROP TABLE allergy_alert_acknowledgements;
            SQL);
    }
};
