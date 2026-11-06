<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, fase 2. Datos que los módulos pacientes, médicos y citas usan y el esquema base no tenía:
// documento de identidad (DPI o pasaporte), contacto, versión de la ficha, horario semanal del médico y
// motivo del último cambio de estado de una cita. Cierra AU-02 (código y nombres en blanco).
return new class extends Migration
{
    protected $connection = 'hospital';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            ALTER TABLE local_patients
                ADD COLUMN document_type text CONSTRAINT local_patients_document_type_check CHECK (document_type IN ('DPI', 'PASAPORTE')),
                ADD COLUMN document_number text,
                ADD COLUMN document_country text CONSTRAINT local_patients_document_country_check CHECK (document_country ~ '^[A-Z]{3}$'),
                ADD COLUMN address text,
                ADD COLUMN emergency_contact_name text,
                ADD COLUMN emergency_contact_phone text,
                ADD COLUMN search_name text NOT NULL DEFAULT '',
                ADD COLUMN version integer NOT NULL DEFAULT 1 CONSTRAINT local_patients_version_check CHECK (version > 0),
                ADD CONSTRAINT local_patients_document_complete_check CHECK ((document_type IS NULL) = (document_number IS NULL)),
                ADD CONSTRAINT local_patients_code_nonblank_check CHECK (btrim(code) <> ''),
                ADD CONSTRAINT local_patients_names_nonblank_check CHECK (btrim(given_name) <> '' AND btrim(family_name) <> '');
            -- Numeración del código de paciente; pertenece a la columna, así se borra con la tabla (migrate:fresh).
            CREATE SEQUENCE local_patient_code_seq START 1 OWNED BY local_patients.code;
            CREATE UNIQUE INDEX local_patients_document_unique ON local_patients (document_type, COALESCE(document_country, ''), document_number) WHERE document_number IS NOT NULL;
            CREATE INDEX local_patients_search_name_index ON local_patients (search_name text_pattern_ops);
            CREATE INDEX local_patients_birth_date_index ON local_patients (birth_date);

            CREATE TABLE doctor_schedules (
                doctor_schedule_id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                doctor_profile_id uuid NOT NULL REFERENCES doctor_profiles(doctor_profile_id) ON DELETE RESTRICT,
                weekday smallint NOT NULL CONSTRAINT doctor_schedules_weekday_check CHECK (weekday BETWEEN 1 AND 7),
                starts_at time NOT NULL,
                ends_at time NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT doctor_schedules_period_check CHECK (ends_at > starts_at),
                CONSTRAINT doctor_schedules_half_hour_check CHECK (extract(minute FROM starts_at) IN (0, 30) AND extract(minute FROM ends_at) IN (0, 30)
                    AND extract(second FROM starts_at) = 0 AND extract(second FROM ends_at) = 0),
                CONSTRAINT doctor_schedules_no_overlap EXCLUDE USING gist (
                    doctor_profile_id WITH =, weekday WITH =,
                    tsrange('2000-01-01'::date + starts_at, '2000-01-01'::date + ends_at, '[)') WITH &&
                )
            );
            CREATE INDEX doctor_schedules_doctor_profile_id_index ON doctor_schedules (doctor_profile_id);

            ALTER TABLE appointments
                ADD COLUMN status_reason text,
                ADD COLUMN status_changed_at timestamptz,
                ADD COLUMN booked_by_user_ref_id uuid REFERENCES local_user_refs(local_user_ref_id) ON DELETE RESTRICT;
            CREATE INDEX appointments_booked_by_user_ref_id_index ON appointments (booked_by_user_ref_id);
            CREATE INDEX appointments_starts_at_index ON appointments (starts_at);
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            DROP INDEX appointments_starts_at_index;
            DROP INDEX appointments_booked_by_user_ref_id_index;
            ALTER TABLE appointments
                DROP COLUMN booked_by_user_ref_id,
                DROP COLUMN status_changed_at,
                DROP COLUMN status_reason;
            DROP TABLE doctor_schedules;
            DROP INDEX local_patients_birth_date_index;
            DROP INDEX local_patients_search_name_index;
            DROP INDEX local_patients_document_unique;
            ALTER TABLE local_patients
                DROP CONSTRAINT local_patients_names_nonblank_check,
                DROP CONSTRAINT local_patients_code_nonblank_check,
                DROP CONSTRAINT local_patients_document_complete_check,
                DROP COLUMN version,
                DROP COLUMN search_name,
                DROP COLUMN emergency_contact_phone,
                DROP COLUMN emergency_contact_name,
                DROP COLUMN address,
                DROP COLUMN document_country,
                DROP COLUMN document_number,
                DROP COLUMN document_type;
            DROP SEQUENCE local_patient_code_seq;
            SQL);
    }
};
