<?php

namespace App\Http\Controllers;

use App\Services\AccesCompteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** ADMIN-02 : routes publiques — « Mot de passe oublié » et définition du mot de passe via un lien. */
class AccesController extends Controller
{
    public function __construct(private readonly AccesCompteService $acces) {}

    /** Réponse identique que le compte existe ou non (pas d'énumération) ; envoi après la réponse. */
    public function oubli(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        dispatch(fn () => $this->acces->demanderOubli($data['email']))->afterResponse();

        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un email vient d\'être envoyé.',
        ], 202);
    }

    /** Vérification non consommante : un clic automatique sur le lien ne l'invalide pas. */
    public function verifier(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string'], 'token' => ['required', 'string']]);

        return $this->acces->lienValide($data['email'], $data['token'])
            ? response()->json(['valide' => true])
            : $this->lienInvalide();
    }

    public function definir(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        return $this->acces->definirMotDePasse($data['email'], $data['token'], $data['password'])
            ? response()->json(['message' => 'Mot de passe enregistré.'])
            : $this->lienInvalide();
    }

    private function lienInvalide(): JsonResponse
    {
        return response()->json(['message' => 'Ce lien n\'est plus valable.', 'code' => 'lien_invalide'], 410);
    }
}
