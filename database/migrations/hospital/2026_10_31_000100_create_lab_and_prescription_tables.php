<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, fase 5. Laboratorio (órdenes, muestras, resultados y validación) y prescripciones.
// Cada ítem de orden y cada receta guardan una COPIA de los datos de la prueba o del medicamento al momento de
// pedirlos (código, nombre, unidad, rangos): si el catálogo cambia después, la historia clínica no cambia.
return new class extends Migration
{
    protected $connection = 'hospital';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            CREATE TABLE lab_orders (
                lab_order_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                local_patient_id uuid NOT NULL REFERENCES local_patients(local_patient_id) ON DELETE RESTRICT,
                care_episode_id uuid REFERENCES care_episodes(care_episode_id) ON DELETE RESTRICT,
                ordered_by_user_ref_id uuid NOT NULL REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                priority text NOT NULL DEFAULT 'routine' CONSTRAINT lab_orders_priority_check CHECK (priority IN ('routine', 'urgent')),
                clinical_indication text NOT NULL CONSTRAINT lab_orders_indication_check CHECK (btrim(clinical_indication) <> ''),
                status text NOT NULL DEFAULT 'ordered' CONSTRAINT lab_orders_status_check CHECK (status IN ('ordered', 'in_process', 'completed', 'cancelled')),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );
            CREATE INDEX lab_orders_local_patient_id_index ON lab_orders (local_patient_id);
            CREATE INDEX lab_orders_care_episode_id_index ON lab_orders (care_episode_id);
            CREATE INDEX lab_orders_ordered_by_user_ref_id_index ON lab_orders (ordered_by_user_ref_id);

            CREATE TABLE lab_order_items (
                lab_order_item_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                lab_order_id uuid NOT NULL REFERENCES lab_orders(lab_order_id) ON DELETE RESTRICT,
                test_code text NOT NULL CONSTRAINT lab_order_items_code_check CHECK (btrim(test_code) <> ''),
                test_name text NOT NULL,
                specimen_type text NOT NULL,
                unit text NOT NULL,
                ref_low numeric, ref_high numeric, critical_low numeric, critical_high numeric,
                status text NOT NULL DEFAULT 'pending' CONSTRAINT lab_order_items_status_check CHECK (status IN ('pending', 'received', 'rejected', 'resulted', 'validated')),
                specimen_code text,
                received_at timestamptz,
                received_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                rejection_reason text,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT lab_order_items_test_once UNIQUE (lab_order_id, test_code),
                CONSTRAINT lab_order_items_specimen_unique UNIQUE (specimen_code),
                CONSTRAINT lab_order_items_rejection_check CHECK (status <> 'rejected' OR btrim(coalesce(rejection_reason, '')) <> '')
            );
            CREATE INDEX lab_order_items_received_by_user_ref_id_index ON lab_order_items (received_by_user_ref_id);
            CREATE INDEX lab_order_items_status_index ON lab_order_items (status);
            CREATE SEQUENCE lab_specimen_seq START 1 OWNED BY lab_order_items.specimen_code;

            CREATE TABLE lab_results (
                lab_result_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                lab_order_item_id uuid NOT NULL REFERENCES lab_order_items(lab_order_item_id) ON DELETE RESTRICT,
                value_numeric numeric NOT NULL,
                unit text NOT NULL,
                flag text NOT NULL CONSTRAINT lab_results_flag_check CHECK (flag IN ('normal', 'low', 'high', 'critical_low', 'critical_high')),
                status text NOT NULL DEFAULT 'captured' CONSTRAINT lab_results_status_check CHECK (status IN ('captured', 'validated', 'rejected', 'superseded')),
                entered_by_user_ref_id uuid NOT NULL REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                validated_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                validated_at timestamptz,
                review_note text,
                version_no integer NOT NULL DEFAULT 1 CONSTRAINT lab_results_version_check CHECK (version_no > 0),
                supersedes_id uuid REFERENCES lab_results(lab_result_id) ON DELETE RESTRICT,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT lab_results_validation_check CHECK ((status = 'validated') = (validated_at IS NOT NULL)),
                CONSTRAINT lab_results_separation_check CHECK (validated_by_user_ref_id IS NULL OR validated_by_user_ref_id <> entered_by_user_ref_id)
            );
            CREATE INDEX lab_results_lab_order_item_id_index ON lab_results (lab_order_item_id);
            CREATE INDEX lab_results_entered_by_user_ref_id_index ON lab_results (entered_by_user_ref_id);
            CREATE INDEX lab_results_validated_by_user_ref_id_index ON lab_results (validated_by_user_ref_id);
            CREATE INDEX lab_results_supersedes_id_index ON lab_results (supersedes_id);
            CREATE UNIQUE INDEX lab_results_one_current ON lab_results (lab_order_item_id) WHERE status IN ('captured', 'validated');

            CREATE TABLE prescriptions (
                prescription_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                local_patient_id uuid NOT NULL REFERENCES local_patients(local_patient_id) ON DELETE RESTRICT,
                care_episode_id uuid REFERENCES care_episodes(care_episode_id) ON DELETE RESTRICT,
                prescriber_user_ref_id uuid NOT NULL REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT,
                medication_code text,
                medication_name text NOT NULL CONSTRAINT prescriptions_medication_check CHECK (btrim(medication_name) <> ''),
                dose text NOT NULL CONSTRAINT prescriptions_dose_check CHECK (btrim(dose) <> ''),
                route text NOT NULL,
                frequency text NOT NULL CONSTRAINT prescriptions_frequency_check CHECK (btrim(frequency) <> ''),
                duration_days integer CONSTRAINT prescriptions_duration_check CHECK (duration_days IS NULL OR duration_days BETWEEN 1 AND 365),
                instructions text,
                status text NOT NULL DEFAULT 'issued' CONSTRAINT prescriptions_status_check CHECK (status IN ('issued', 'cancelled')),
                allergy_override_reason text,
                cancel_reason text,
                cancelled_at timestamptz,
                issued_at timestamptz NOT NULL DEFAULT now(),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT prescriptions_cancel_check CHECK ((status = 'cancelled') = (cancelled_at IS NOT NULL) AND (status <> 'cancelled' OR btrim(coalesce(cancel_reason, '')) <> ''))
            );
            CREATE INDEX prescriptions_local_patient_id_index ON prescriptions (local_patient_id);
            CREATE INDEX prescriptions_care_episode_id_index ON prescriptions (care_episode_id);
            CREATE INDEX prescriptions_prescriber_user_ref_id_index ON prescriptions (prescriber_user_ref_id);
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            DROP TABLE prescriptions;
            DROP TABLE lab_results;
            DROP TABLE lab_order_items;
            DROP TABLE lab_orders;
            SQL);
    }
};
