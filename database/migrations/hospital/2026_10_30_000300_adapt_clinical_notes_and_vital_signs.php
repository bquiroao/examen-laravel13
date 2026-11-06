<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, fase 4. Dos ajustes al esquema clínico base para que el expediente funcione:
// 1. Notas: la versión era única POR EXPEDIENTE y toda nota nueva debe ser la versión 1, así que un paciente solo podía
//    tener una nota. La versión pasa a ser por cadena de corrección (sigue habiendo un solo sucesor por nota).
// 2. Signos vitales: el módulo signos-vitales-alertas los registra enfermería; el esquema solo aceptaba al médico
//    asignado al episodio. Se acepta también a enfermería con una copia de usuario activa en el hospital.
return new class extends Migration
{
    protected $connection = 'hospital';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            ALTER TABLE clinical_notes DROP CONSTRAINT clinical_notes_record_version_unique;
            CREATE INDEX clinical_notes_record_created_index ON clinical_notes (medical_record_id, created_at);
            CREATE INDEX vital_signs_record_measured_index ON vital_signs (medical_record_id, measured_at);

            ALTER TABLE care_episodes ADD COLUMN appointment_id uuid REFERENCES appointments(appointment_id) ON DELETE RESTRICT;
            CREATE INDEX care_episodes_appointment_id_index ON care_episodes (appointment_id);

            CREATE OR REPLACE FUNCTION enforce_vital_sign_integrity() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM medical_records r JOIN care_episodes e ON e.local_patient_id = r.local_patient_id WHERE r.medical_record_id = NEW.medical_record_id AND e.care_episode_id = NEW.care_episode_id) THEN RAISE EXCEPTION 'vital_signs_record_episode_patient_trigger'; END IF;
                IF NOT EXISTS (SELECT 1 FROM doctor_profiles p JOIN care_assignments a ON a.doctor_profile_id = p.doctor_profile_id WHERE p.local_user_ref_id = NEW.recorded_by_user_ref_id AND a.care_episode_id = NEW.care_episode_id AND a.status = 'active' AND a.valid_from <= now() AND a.valid_to IS NULL)
                    AND NOT EXISTS (SELECT 1 FROM local_user_refs u WHERE u.local_user_ref_id = NEW.recorded_by_user_ref_id AND u.role_code = 'enfermeria' AND u.status = 'active') THEN RAISE EXCEPTION 'vital_signs_author_assignment_trigger'; END IF;
                IF NEW.supersedes_id = NEW.vital_sign_id THEN RAISE EXCEPTION 'vital_signs_self_supersedes_trigger'; END IF;
                IF (NEW.supersedes_id IS NULL AND NEW.version_no <> 1) OR (NEW.supersedes_id IS NOT NULL AND NEW.version_no <= 1) THEN RAISE EXCEPTION 'vital_signs_version_coherence_trigger'; END IF;
                IF NEW.supersedes_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vital_signs v WHERE v.vital_sign_id = NEW.supersedes_id AND v.medical_record_id = NEW.medical_record_id AND v.care_episode_id = NEW.care_episode_id AND v.version_no = NEW.version_no - 1) THEN RAISE EXCEPTION 'vital_signs_supersedes_version_trigger'; END IF;
                IF TG_OP = 'UPDATE' AND OLD.status = 'validated' AND to_jsonb(NEW) IS DISTINCT FROM to_jsonb(OLD) THEN RAISE EXCEPTION 'vital_signs_validated_immutable'; END IF;
                RETURN NEW;
            END; $$;
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_vital_sign_integrity() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                 IF NOT EXISTS (SELECT 1 FROM medical_records r JOIN care_episodes e ON e.local_patient_id = r.local_patient_id WHERE r.medical_record_id = NEW.medical_record_id AND e.care_episode_id = NEW.care_episode_id) THEN RAISE EXCEPTION 'vital_signs_record_episode_patient_trigger'; END IF;
                 IF NOT EXISTS (SELECT 1 FROM doctor_profiles p JOIN care_assignments a ON a.doctor_profile_id = p.doctor_profile_id WHERE p.local_user_ref_id = NEW.recorded_by_user_ref_id AND a.care_episode_id = NEW.care_episode_id AND a.status = 'active' AND a.valid_from <= now() AND a.valid_to IS NULL) THEN RAISE EXCEPTION 'vital_signs_author_assignment_trigger'; END IF;
                IF NEW.supersedes_id = NEW.vital_sign_id THEN RAISE EXCEPTION 'vital_signs_self_supersedes_trigger'; END IF;
                IF (NEW.supersedes_id IS NULL AND NEW.version_no <> 1) OR (NEW.supersedes_id IS NOT NULL AND NEW.version_no <= 1) THEN RAISE EXCEPTION 'vital_signs_version_coherence_trigger'; END IF;
                IF NEW.supersedes_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vital_signs v WHERE v.vital_sign_id = NEW.supersedes_id AND v.medical_record_id = NEW.medical_record_id AND v.care_episode_id = NEW.care_episode_id AND v.version_no = NEW.version_no - 1) THEN RAISE EXCEPTION 'vital_signs_supersedes_version_trigger'; END IF;
                IF TG_OP = 'UPDATE' AND OLD.status = 'validated' AND to_jsonb(NEW) IS DISTINCT FROM to_jsonb(OLD) THEN RAISE EXCEPTION 'vital_signs_validated_immutable'; END IF;
                RETURN NEW;
            END; $$;
            DROP INDEX care_episodes_appointment_id_index;
            ALTER TABLE care_episodes DROP COLUMN appointment_id;
            DROP INDEX vital_signs_record_measured_index;
            DROP INDEX clinical_notes_record_created_index;
            ALTER TABLE clinical_notes ADD CONSTRAINT clinical_notes_record_version_unique UNIQUE (medical_record_id, version_no);
            SQL);
    }
};
