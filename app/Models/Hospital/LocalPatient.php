<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LocalPatient extends HospitalModel
{
    protected $primaryKey = 'local_patient_id';

    protected $fillable = [
        'global_id', 'code', 'given_name', 'family_name', 'birth_date', 'sex', 'contact', 'status', 'mpi_link_status',
        'document_type', 'document_number', 'document_country', 'address', 'emergency_contact_name', 'emergency_contact_phone',
        'search_name', 'version',
    ];

    protected $casts = ['birth_date' => 'date:Y-m-d', 'version' => 'integer'];

    public const SEXES = ['female' => 'Femenino', 'male' => 'Masculino', 'other' => 'Otro', 'unknown' => 'No indicado'];

    public const STATUSES = ['active' => 'Activo', 'inactive' => 'Inactivo', 'deceased' => 'Fallecido'];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'local_patient_id');
    }

    public function fullName(): string
    {
        return trim($this->given_name.' '.$this->family_name);
    }

    /** Nombre para buscar: minúsculas, sin tildes y con un solo espacio (módulo pacientes, Texto::normalizar). */
    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', Str::lower(Str::ascii($text))));
    }

    /** Documento enmascarado: solo los últimos 4 caracteres (módulo pacientes). */
    public function maskedDocument(): ?string
    {
        if ($this->document_number === null) {
            return null;
        }

        return str_repeat('•', max(0, strlen($this->document_number) - 4)).substr($this->document_number, -4);
    }

    public function age(): int
    {
        return (int) $this->birth_date->age;
    }
}
