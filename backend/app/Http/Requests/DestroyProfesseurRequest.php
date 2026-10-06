<?php

namespace App\Http\Requests;

use App\Models\Professeur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * PROF-02 : suppression d'un professeur. Sans `force`, aucune donnée attendue. Avec `force`, le motif
 * (≥ 10 caractères) et « Prénom Nom » recopié sont obligatoires (casse, espaces et accents ignorés).
 */
class DestroyProfesseurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('professeur'));
    }

    public function rules(): array
    {
        return [
            'force' => ['sometimes', 'boolean'],
            'motif' => ['exclude_unless:force,true', 'required', 'string', 'min:10', 'max:1000'],
            'confirmation_nom' => ['exclude_unless:force,true', 'required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Indiquez le motif de la suppression.',
            'motif.min' => 'Le motif doit contenir au moins 10 caractères.',
            'confirmation_nom.required' => 'Recopiez le nom du professeur pour confirmer.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if (! $this->boolean('force') || $v->errors()->has('confirmation_nom')) {
                return;
            }
            /** @var Professeur $professeur */
            $professeur = $this->route('professeur');
            $attendu = trim("{$professeur->prenom} {$professeur->nom}");

            if (self::normaliser((string) $this->input('confirmation_nom')) !== self::normaliser($attendu)) {
                $v->errors()->add('confirmation_nom', "Le nom ne correspond pas (« {$attendu} »).");
            }
        }];
    }

    public function forcer(): bool
    {
        return $this->boolean('force');
    }

    public static function normaliser(string $nom): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', Str::ascii($nom))));
    }
}
