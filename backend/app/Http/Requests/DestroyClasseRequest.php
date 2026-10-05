<?php

namespace App\Http\Requests;

use App\Http\Resources\ClasseResource;
use App\Models\Classe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * CLS-08 : suppression d'une classe. Sans `force`, aucune donnée attendue. Avec `force`, le motif
 * (≥ 10 caractères) et le nom de la classe (titre des cours) recopié sont obligatoires.
 */
class DestroyClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('classe'));
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
            'motif.min' => 'Le motif doit faire au moins 10 caractères.',
            'confirmation_nom.required' => 'Recopiez le nom de la classe pour confirmer.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if (! $this->boolean('force') || $v->errors()->has('confirmation_nom')) {
                return;
            }
            /** @var Classe $classe */
            $classe = $this->route('classe');
            $attendu = ClasseResource::titreDe($classe->loadMissing('periodes.cours'));

            if (self::normaliser((string) $this->input('confirmation_nom')) !== self::normaliser($attendu)) {
                $v->errors()->add('confirmation_nom', "Le nom saisi ne correspond pas au nom de la classe (« {$attendu} »).");
            }
        }];
    }

    public function forcer(): bool
    {
        return $this->boolean('force');
    }

    /** Casse, espaces multiples et « -> » (pour « → ») ignorés. */
    private static function normaliser(string $nom): string
    {
        $nom = (string) preg_replace('/\s*→\s*/u', ' → ', str_replace('->', '→', $nom));

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $nom)));
    }
}
