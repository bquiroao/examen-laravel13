<?php

// Catálogo de Hospital Nido. Cada permiso indica el módulo de docs/modulos/ del que sale.
// RbacCatalogSeeder lo copia a las tablas roles, permissions y role_permissions de la base central.

return [

    'name' => 'Hospital Nido',

    // Token con el que el pipeline de CI registra sus ejecuciones (POST /api/v1/calidad/ci/ejecuciones). Vacío = deshabilitado.
    'ci_token' => env('NIDO_CI_TOKEN', ''),

    'auth' => [
        // Módulo usuarios/tenants: contraseña de 12 o más caracteres con letra y dígito.
        'password_min_length' => 12,
        // Límite de intentos por cuenta+IP, con 429 y Retry-After (sin bloqueo permanente).
        'max_login_attempts' => 5,
        'login_decay_seconds' => 900,
    ],

    'permissions' => [
        // Plataforma
        'usuarios.ver' => ['gestion-usuarios-tenants-autenticacion', 'Ver usuarios del hospital'],
        'usuarios.gestionar' => ['gestion-usuarios-tenants-autenticacion', 'Crear y desactivar usuarios del hospital'],
        'roles.ver' => ['rbac', 'Ver roles y la matriz de permisos'],
        'roles.asignar' => ['rbac', 'Asignar roles a usuarios del hospital'],
        'roles.revocar' => ['rbac', 'Revocar roles de usuarios del hospital'],
        'roles.configurar' => ['rbac', 'Cambiar los permisos de cada rol (nivel red)'],
        'auditoria.consultar' => ['gobernanza-auditoria', 'Consultar la bitácora de movimientos'],
        'contratos_api.ver' => ['contratos-api', 'Ver la documentación de la API'],
        'base_datos.verificar' => ['verificacion-bd-postgresql', 'Ver el estado y la integridad de la base de datos'],
        // Pacientes y agenda
        'pacientes.ver' => ['pacientes', 'Buscar y ver pacientes'],
        'pacientes.gestionar' => ['pacientes', 'Registrar y editar pacientes'],
        'citas.ver' => ['citas-medicas', 'Ver la agenda de citas'],
        'citas.gestionar' => ['citas-medicas', 'Programar, reprogramar y cancelar citas'],
        'medicos.ver' => ['medicos-especialidades', 'Ver médicos, especialidades y disponibilidad'],
        'medicos.gestionar' => ['medicos-especialidades', 'Gestionar médicos, especialidades y disponibilidad'],
        // Camas
        'camas.ver' => ['catalogo-salas-camas', 'Ver salas y camas'],
        'camas.gestionar' => ['catalogo-salas-camas', 'Gestionar el catálogo de salas y camas'],
        'admisiones.gestionar' => ['admision-hospitalaria-asignacion-cama', 'Admitir pacientes y asignar cama'],
        'traslados.gestionar' => ['traslados-altas-camas', 'Trasladar, dar de alta y liberar camas'],
        'ocupacion.ver' => ['dashboard-ocupacion', 'Ver el tablero de ocupación'],
        // Clínico
        'expediente.leer' => ['expediente-medico', 'Leer el expediente clínico'],
        'notas.registrar' => ['notas-soap-diagnosticos', 'Registrar notas SOAP y diagnósticos'],
        'notas.firmar' => ['notas-soap-diagnosticos', 'Firmar y corregir notas SOAP'],
        'signos_vitales.registrar' => ['signos-vitales-alertas', 'Registrar signos vitales'],
        'alergias.gestionar' => ['alergias-clinicas-alerta-visual-expediente', 'Registrar y confirmar alergias'],
        'prescripciones.crear' => ['prescripciones-alergias', 'Emitir prescripciones'],
        'prescripciones.anular' => ['prescripciones-alergias', 'Anular prescripciones'],
        'medicamentos.ver' => ['catalogo-medicamentos', 'Consultar el catálogo de medicamentos'],
        'medicamentos.gestionar' => ['catalogo-medicamentos', 'Gestionar el catálogo de medicamentos'],
        'alertas.ver' => ['alertas-criticas-notificaciones-internas', 'Ver alertas críticas'],
        'alertas.gestionar' => ['alertas-criticas-notificaciones-internas', 'Reconocer y escalar alertas críticas'],
        // Módulo alertas-criticas-notificaciones-internas: cerrar con nota (RN-09) y configurar los plazos (CU-08).
        'alertas.cerrar' => ['alertas-criticas-notificaciones-internas', 'Cerrar alertas críticas acusadas, con nota'],
        'alertas.configurar' => ['alertas-criticas-notificaciones-internas', 'Configurar los plazos de acuse de las alertas'],
        // Laboratorio
        'pruebas_lab.ver' => ['catalogo-pruebas-laboratorio', 'Consultar el catálogo de pruebas'],
        'pruebas_lab.gestionar' => ['catalogo-pruebas-laboratorio', 'Gestionar el catálogo de pruebas'],
        'ordenes_lab.crear' => ['ordenes-laboratorio-emr', 'Ordenar pruebas de laboratorio'],
        'muestras.recibir' => ['recepcion-muestras', 'Recibir muestras y asignar códigos'],
        'resultados.registrar' => ['ingreso-resultados-laboratorio', 'Ingresar resultados de laboratorio'],
        'resultados.validar' => ['validacion-resultados-bioquimico', 'Validar resultados como bioquímico'],
        // Reportes
        'dashboard.ver' => ['dashboard-base', 'Ver el tablero de inicio'],
        'reportes.ver' => ['reportes-analytics-basicos', 'Ver reportes e indicadores'],
        'reportes.citas_propias' => ['reportes-analytics-basicos', 'Ver el indicador de citas, solo con sus propias citas'],
        'reportes.exportar' => ['reportes-operativos-exportables', 'Exportar reportes operativos'],
        // Calidad
        'calidad.gestionar' => ['qa-regresion-defectos', 'Gestionar campañas de regresión y defectos'],
        'versiones.gestionar' => ['qa-e2e-ci-despliegue', 'Gestionar ejecuciones E2E, quality gate y despliegues'],
        // Acceso móvil/escritorio
        'movil.usar' => ['investigacion-prototipo-nativephp', 'Usar la app móvil o de escritorio'],
    ],

    'roles' => [
        'admin_red' => ['Administrador de red', 'network', '*'],
        'admin_hospital' => ['Administrador de hospital', 'hospital', [
            'usuarios.ver', 'usuarios.gestionar', 'roles.ver', 'roles.asignar', 'roles.revocar', 'auditoria.consultar',
            'contratos_api.ver', 'base_datos.verificar', 'medicos.ver', 'medicos.gestionar', 'camas.ver', 'camas.gestionar',
            'ocupacion.ver', 'medicamentos.ver', 'medicamentos.gestionar', 'pruebas_lab.ver', 'pruebas_lab.gestionar',
            'dashboard.ver', 'reportes.ver', 'reportes.exportar',
            'alertas.configurar', // módulo alertas-criticas-notificaciones-internas
        ]],
        'direccion' => ['Dirección médica', 'hospital', [
            'pacientes.ver', 'citas.ver', 'medicos.ver', 'camas.ver', 'ocupacion.ver', 'alertas.ver', 'dashboard.ver',
            'reportes.ver', 'reportes.exportar', 'auditoria.consultar',
            'alertas.cerrar', 'alertas.configurar', // módulo alertas-criticas-notificaciones-internas
        ]],
        'medico' => ['Médico', 'hospital', [
            'pacientes.ver', 'citas.ver', 'citas.gestionar', 'medicos.ver', 'camas.ver', 'ocupacion.ver', 'admisiones.gestionar', 'traslados.gestionar',
            'expediente.leer', 'notas.registrar', 'notas.firmar', 'signos_vitales.registrar', 'alergias.gestionar',
            'prescripciones.crear', 'prescripciones.anular', 'medicamentos.ver', 'alertas.ver', 'alertas.gestionar',
            'pruebas_lab.ver', 'ordenes_lab.crear', 'dashboard.ver', 'movil.usar',
            'reportes.citas_propias',
            'alertas.cerrar', // módulo alertas-criticas-notificaciones-internas
        ]],
        'enfermeria' => ['Enfermería', 'hospital', [
            'pacientes.ver', 'citas.ver', 'camas.ver', 'ocupacion.ver', 'traslados.gestionar', 'expediente.leer',
            'signos_vitales.registrar', 'alergias.gestionar', 'medicamentos.ver', 'alertas.ver', 'alertas.gestionar',
            'dashboard.ver', 'movil.usar',
        ]],
        'recepcion' => ['Recepción y admisión', 'hospital', [
            'pacientes.ver', 'pacientes.gestionar', 'citas.ver', 'citas.gestionar', 'medicos.ver', 'camas.ver',
            'admisiones.gestionar', 'ocupacion.ver', 'dashboard.ver',
        ]],
        'laboratorio' => ['Técnico de laboratorio', 'hospital', [
            'pacientes.ver', 'pruebas_lab.ver', 'muestras.recibir', 'resultados.registrar', 'alertas.ver', 'dashboard.ver',
        ]],
        'bioquimico' => ['Bioquímico', 'hospital', [
            'pacientes.ver', 'expediente.leer', 'pruebas_lab.ver', 'pruebas_lab.gestionar', 'muestras.recibir',
            'resultados.registrar', 'resultados.validar', 'alertas.ver', 'alertas.gestionar', 'dashboard.ver',
        ]],
        'farmacia' => ['Farmacia', 'hospital', [
            'pacientes.ver', 'medicamentos.ver', 'medicamentos.gestionar', 'alertas.ver', 'dashboard.ver',
        ]],
        'calidad' => ['Calidad y QA', 'hospital', [
            'auditoria.consultar', 'contratos_api.ver', 'base_datos.verificar', 'calidad.gestionar', 'versiones.gestionar',
            'dashboard.ver', 'reportes.ver',
        ]],
    ],
];
