<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        // PROF-01 RG-2 : le refus « désactivé » n'est révélé qu'avec un mot de passe correct.
        $inactif = $user->isProfesseur()
            ? $user->professeur?->statut === 'inactif'
            : $user->statut === 'inactif';

        if ($inactif) {
            $message = $user->isProfesseur() ? 'Ce compte a été archivé. Contactez la direction de votre école.' : 'Ce compte est désactivé. Contactez la direction.';

            return response()->json(['message' => $message, 'code' => 'compte_desactive'], 403);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user->load('professeur'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request)
    {
        // RGPD-01 : le professeur lit son propre IBAN (masqué partout ailleurs, y compris login).
        $user = $request->user()->load('professeur');
        $user->professeur?->makeVisible('compte_bancaire');

        return $user;
    }

    public function changerMotDePasse(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Mot de passe actuel incorrect.']]);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        return response()->json($user->load('professeur'));
    }
}
