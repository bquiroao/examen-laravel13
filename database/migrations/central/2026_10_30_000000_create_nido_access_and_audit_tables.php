<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hospital Nido, fase 1. Credenciales locales (módulo usuarios/tenants), catálogo RBAC (módulo RBAC)
// y bitácora encadenada de solo inserción (módulo gobernanza-auditoría, database/integracion-central.sql).
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            CREATE TABLE user_credentials (
                user_id uuid PRIMARY KEY REFERENCES central_users(user_id) ON DELETE RESTRICT,
                password_hash text NOT NULL CHECK (btrim(password_hash) <> ''),
                password_changed_at timestamptz NOT NULL DEFAULT now(),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE roles (
                role_code text PRIMARY KEY CHECK (role_code ~ '^[a-z][a-z_]{1,39}$'),
                name text NOT NULL CHECK (btrim(name) <> ''),
                description text NOT NULL DEFAULT '',
                scope text NOT NULL DEFAULT 'hospital' CHECK (scope IN ('hospital', 'network')),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE permissions (
                permission_code text PRIMARY KEY CHECK (permission_code ~ '^[a-z][a-z_]*\.[a-z][a-z_]*$'),
                module text NOT NULL CHECK (btrim(module) <> ''),
                description text NOT NULL CHECK (btrim(description) <> ''),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE role_permissions (
                role_code text NOT NULL REFERENCES roles(role_code) ON DELETE RESTRICT,
                permission_code text NOT NULL REFERENCES permissions(permission_code) ON DELETE RESTRICT,
                created_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (role_code, permission_code)
            );
            CREATE INDEX role_permissions_permission_code_index ON role_permissions (permission_code);

            CREATE TABLE audit_logs (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                emisor text NOT NULL CHECK (btrim(emisor) <> ''),
                event_id uuid NOT NULL,
                hospital_id uuid REFERENCES hospitals(hospital_id) ON DELETE RESTRICT,
                usuario_id uuid,
                modulo text NOT NULL CHECK (btrim(modulo) <> ''),
                accion text NOT NULL CHECK (btrim(accion) <> ''),
                ocurrido_en timestamptz NOT NULL,
                registrado_en timestamptz NOT NULL DEFAULT now(),
                resultado text NOT NULL CHECK (resultado IN ('exito', 'denegado', 'error')),
                recurso text,
                correlation_id uuid,
                ip inet,
                detalle jsonb NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(detalle) = 'object'),
                secuencia bigint NOT NULL CHECK (secuencia > 0),
                sello char(64) NOT NULL,
                corrige_a bigint REFERENCES audit_logs(id) ON DELETE RESTRICT,
                motivo_correccion text,
                CONSTRAINT audit_logs_correccion_completa CHECK ((corrige_a IS NULL) = (motivo_correccion IS NULL)),
                CONSTRAINT audit_logs_evento_unico UNIQUE (emisor, event_id)
            );
            CREATE UNIQUE INDEX audit_logs_cadena_unica ON audit_logs (COALESCE(hospital_id::text, '*'), secuencia);
            CREATE UNIQUE INDEX audit_logs_una_correccion ON audit_logs (corrige_a) WHERE corrige_a IS NOT NULL;
            CREATE INDEX audit_logs_hospital_fecha ON audit_logs (hospital_id, ocurrido_en DESC, id DESC);
            CREATE INDEX audit_logs_usuario_fecha ON audit_logs (usuario_id, ocurrido_en DESC);
            CREATE INDEX audit_logs_modulo_fecha ON audit_logs (modulo, ocurrido_en DESC);
            CREATE INDEX audit_logs_recurso_fecha ON audit_logs (recurso, ocurrido_en DESC);

            CREATE OR REPLACE FUNCTION audit_logs_solo_insercion() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs es de solo inserción (%)', TG_OP USING ERRCODE = 'insufficient_privilege';
            END;
            $$;
            CREATE TRIGGER audit_logs_sin_update_delete BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION audit_logs_solo_insercion();
            CREATE TRIGGER audit_logs_sin_truncate BEFORE TRUNCATE ON audit_logs FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_solo_insercion();
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
            DROP TRIGGER audit_logs_sin_truncate ON audit_logs;
            DROP TRIGGER audit_logs_sin_update_delete ON audit_logs;
            DROP TABLE audit_logs;
            DROP FUNCTION audit_logs_solo_insercion();
            DROP TABLE role_permissions;
            DROP TABLE permissions;
            DROP TABLE roles;
            DROP TABLE user_credentials;
            SQL);
    }
};
