<?php

// Catálogos PROVISIONALES de pruebas de laboratorio y de medicamentos, para que laboratorio y prescripciones
// funcionen desde ya. Los módulos catalogo-pruebas-laboratorio y catalogo-medicamentos los reemplazan con sus tablas
// implementando App\Services\Catalogs\LabTestDirectory y MedicationDirectory (README, secciones 7.1 y 7.6).
// Rangos de referencia de adulto, orientativos.

return [

    'pruebas_laboratorio' => [
        ['code' => 'HB', 'name' => 'Hemoglobina', 'specimen_type' => 'Sangre total (EDTA)', 'unit' => 'g/dL', 'ref_low' => 12.0, 'ref_high' => 17.5, 'critical_low' => 7.0, 'critical_high' => 20.0],
        ['code' => 'LEU', 'name' => 'Leucocitos', 'specimen_type' => 'Sangre total (EDTA)', 'unit' => '10³/µL', 'ref_low' => 4.5, 'ref_high' => 11.0, 'critical_low' => 2.0, 'critical_high' => 30.0],
        ['code' => 'PLT', 'name' => 'Plaquetas', 'specimen_type' => 'Sangre total (EDTA)', 'unit' => '10³/µL', 'ref_low' => 150, 'ref_high' => 450, 'critical_low' => 50, 'critical_high' => 1000],
        ['code' => 'GLU', 'name' => 'Glucosa en ayunas', 'specimen_type' => 'Suero', 'unit' => 'mg/dL', 'ref_low' => 70, 'ref_high' => 100, 'critical_low' => 50, 'critical_high' => 400],
        ['code' => 'CREA', 'name' => 'Creatinina', 'specimen_type' => 'Suero', 'unit' => 'mg/dL', 'ref_low' => 0.6, 'ref_high' => 1.3, 'critical_low' => null, 'critical_high' => 5.0],
        ['code' => 'UREA', 'name' => 'Nitrógeno ureico (BUN)', 'specimen_type' => 'Suero', 'unit' => 'mg/dL', 'ref_low' => 7, 'ref_high' => 20, 'critical_low' => null, 'critical_high' => 100],
        ['code' => 'NA', 'name' => 'Sodio', 'specimen_type' => 'Suero', 'unit' => 'mmol/L', 'ref_low' => 135, 'ref_high' => 145, 'critical_low' => 120, 'critical_high' => 160],
        ['code' => 'K', 'name' => 'Potasio', 'specimen_type' => 'Suero', 'unit' => 'mmol/L', 'ref_low' => 3.5, 'ref_high' => 5.1, 'critical_low' => 2.5, 'critical_high' => 6.5],
        ['code' => 'PCR', 'name' => 'Proteína C reactiva', 'specimen_type' => 'Suero', 'unit' => 'mg/L', 'ref_low' => 0, 'ref_high' => 5, 'critical_low' => null, 'critical_high' => null],
        ['code' => 'HBA1C', 'name' => 'Hemoglobina glicosilada', 'specimen_type' => 'Sangre total (EDTA)', 'unit' => '%', 'ref_low' => 4.0, 'ref_high' => 5.6, 'critical_low' => null, 'critical_high' => null],
    ],

    'medicamentos' => [
        ['code' => 'AMOX500', 'name' => 'Amoxicilina 500 mg cápsula', 'presentation' => 'Cápsula 500 mg', 'route' => 'oral', 'groups' => ['penicilinas']],
        ['code' => 'AMPI1G', 'name' => 'Ampicilina 1 g inyectable', 'presentation' => 'Vial 1 g', 'route' => 'intravenosa', 'groups' => ['penicilinas']],
        ['code' => 'PENBEN', 'name' => 'Penicilina G benzatínica 1.2 MUI', 'presentation' => 'Vial 1.2 MUI', 'route' => 'intramuscular', 'groups' => ['penicilinas']],
        ['code' => 'CEFTRI1G', 'name' => 'Ceftriaxona 1 g inyectable', 'presentation' => 'Vial 1 g', 'route' => 'intravenosa', 'groups' => ['cefalosporinas']],
        ['code' => 'AZI500', 'name' => 'Azitromicina 500 mg tableta', 'presentation' => 'Tableta 500 mg', 'route' => 'oral', 'groups' => ['macrolidos']],
        ['code' => 'TMPSMX', 'name' => 'Trimetoprim-sulfametoxazol 160/800 mg', 'presentation' => 'Tableta', 'route' => 'oral', 'groups' => ['sulfonamidas']],
        ['code' => 'PARA500', 'name' => 'Acetaminofén 500 mg tableta', 'presentation' => 'Tableta 500 mg', 'route' => 'oral', 'groups' => []],
        ['code' => 'IBU400', 'name' => 'Ibuprofeno 400 mg tableta', 'presentation' => 'Tableta 400 mg', 'route' => 'oral', 'groups' => ['aines']],
        ['code' => 'ASA100', 'name' => 'Ácido acetilsalicílico 100 mg', 'presentation' => 'Tableta 100 mg', 'route' => 'oral', 'groups' => ['aines']],
        ['code' => 'OMEP20', 'name' => 'Omeprazol 20 mg cápsula', 'presentation' => 'Cápsula 20 mg', 'route' => 'oral', 'groups' => []],
        ['code' => 'METF850', 'name' => 'Metformina 850 mg tableta', 'presentation' => 'Tableta 850 mg', 'route' => 'oral', 'groups' => []],
        ['code' => 'LOSA50', 'name' => 'Losartán 50 mg tableta', 'presentation' => 'Tableta 50 mg', 'route' => 'oral', 'groups' => []],
        ['code' => 'SALB', 'name' => 'Salbutamol inhalador 100 mcg', 'presentation' => 'Inhalador', 'route' => 'inhalada', 'groups' => []],
    ],

    // Palabras con las que se reconoce cada familia en el alérgeno registrado (alergias cruzadas).
    'familias_alergia' => [
        'penicilinas' => ['penicilina', 'amoxicilina', 'ampicilina', 'betalactam'],
        'cefalosporinas' => ['cefalosporina', 'ceftriaxona', 'cefalexina'],
        'sulfonamidas' => ['sulfa', 'sulfonamida', 'sulfametoxazol'],
        'aines' => ['aine', 'ibuprofeno', 'aspirina', 'acetilsalicilico', 'diclofenaco'],
        'macrolidos' => ['macrolido', 'azitromicina', 'claritromicina', 'eritromicina'],
    ],
];
