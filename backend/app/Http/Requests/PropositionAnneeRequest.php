<?php

namespace App\Http\Requests;

use App\Models\AnneeScolaire;
use Illuminate\Foundation\Http\FormRequest;

/** GET /annees-scolaires/proposition?libelle=2027-2028 (libellé facultatif). */
class PropositionAnneeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AnneeScolaire::class);
    }

    public function rules(): array
    {
        return ['libelle' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}-\d{4}$/']];
    }
}
