<?php

namespace App\Http\Controllers;

use App\Models\ParametreSite;
use App\Models\SignatureSpecimen;
use App\Models\TimesheetParametre;
use App\Services\SignatureNumeriqueService;
use App\Services\SignatureSpecimenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** SIG-01 : signature du professeur, vérification publique d'une signature et paramètre de vérification. */
class SignatureController extends Controller
{
    public function __construct(
        private readonly SignatureSpecimenService $specimens,
        private readonly SignatureNumeriqueService $signatures,
    ) {}

    // Professeur : sa signature enregistrée (ou null).
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->isProfesseur(), 403, 'Action non autorisée.');
        $s = SignatureSpecimen::where('user_id', $request->user()->id)->first();

        return response()->json(['data' => $s ? $this->format($s) : null]);
    }

    // Professeur : crée ou remplace sa signature (PNG transparent + consentement).
    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->isProfesseur(), 403, 'Action non autorisée.');
        $v = $request->validate([
            'type' => ['required', Rule::in(SignatureSpecimen::TYPES)],
            'texte' => ['nullable', 'string', 'max:60', Rule::requiredIf(fn () => $request->input('type') !== 'dessin')],
            'police' => ['nullable', Rule::in(SignatureSpecimen::POLICES), Rule::requiredIf(fn () => $request->input('type') !== 'dessin')],
            'couleur' => ['required', Rule::in(SignatureSpecimen::COULEURS)],
            'image' => ['required', 'string', 'max:'.(int) ceil(SignatureSpecimenService::POIDS_MAX * 1.4)],
            'consentement' => ['accepted'],
        ], ['consentement.accepted' => 'Cochez la case pour reconnaître cette image comme votre signature.']);

        return response()->json(['data' => $this->format($this->specimens->enregistrer($request->user(), $v))]);
    }

    // Public (sans connexion) : vérifie un identifiant « SIG-… » imprimé sur une fiche.
    public function verifier(string $publicId): JsonResponse
    {
        $resultat = $this->signatures->verificationPublique($publicId);

        return $resultat ? response()->json(['data' => $resultat]) : response()->json(['message' => 'Signature introuvable.'], 404);
    }

    // Staff : le paramètre « vérification publique » (s'applique aux signatures émises ensuite).
    public function parametres(): JsonResponse
    {
        Gate::authorize('viewAny', TimesheetParametre::class);

        return response()->json(['data' => ['verification_publique' => ParametreSite::booleen(ParametreSite::SIGNATURE_VERIFICATION_PUBLIQUE)]]);
    }

    public function enregistrerParametres(Request $request): JsonResponse
    {
        Gate::authorize('update', TimesheetParametre::class);
        $v = $request->validate(['verification_publique' => ['required', 'boolean']]);
        ParametreSite::definir(ParametreSite::SIGNATURE_VERIFICATION_PUBLIQUE, (bool) $v['verification_publique'], $request->user());

        return $this->parametres();
    }

    private function format(SignatureSpecimen $s): array
    {
        return [
            'type' => $s->type, 'texte' => $s->texte, 'police' => $s->police, 'couleur' => $s->couleur,
            'image' => $this->specimens->dataUrl($s), 'consenti_at' => $s->consenti_at, 'updated_at' => $s->updated_at,
        ];
    }
}
