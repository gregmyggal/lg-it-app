<?php

namespace App\Http\Requests;

use App\Models\Timesheet;
use App\Services\TimesheetService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une saisie par le professeur connecté : liée à une session (champs déduits de la session) ou libre
 * (R-T3-4, R-T3-5). `professeur_id` n'est jamais accepté du client.
 */
class StoreTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Timesheet::class);
    }

    public function rules(): array
    {
        return [
            'course_session_id' => ['nullable', 'integer', 'exists:course_sessions,id'],
            'type_activite' => ['sometimes', Rule::in(TimesheetService::TYPES)],
            'nombre_heures' => ['required_without:course_session_id', 'nullable', 'numeric', 'min:0.5', 'max:24'],
            'date_prestation' => ['required_without:course_session_id', 'nullable', 'date_format:Y-m-d'],
            'cours_id' => ['nullable', 'integer', 'exists:cours,id'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'course_session_id' => 'session',
            'type_activite' => 'type d\'activité',
            'nombre_heures' => 'durée',
            'date_prestation' => 'date',
            'cours_id' => 'cours',
        ];
    }
}
