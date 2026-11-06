<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SwitchHospitalRequest extends FormRequest
{
    public function rules(): array
    {
        return ['hospital_id' => ['required', 'uuid']];
    }
}
