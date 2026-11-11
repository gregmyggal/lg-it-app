<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\DepotFichier;
use App\Models\DepotPartage;
use App\Models\User;
use App\Support\FrontendUrl;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** DEP-01 T2 : un lien = une sélection de fichiers (jamais l'espace entier), valable 7 jours, régénérable, révocable. */
class DepotPartageService
{
    public const VALIDITE_JOURS = 7;

    /** @param  array<int, int>  $fichierIds */
    public function creer(Classe $classe, array $fichierIds, User $auteur): DepotPartage
    {
        $fichierIds = array_values(array_unique($fichierIds));
        $fichiers = DepotFichier::whereIn('id', $fichierIds)
            ->whereHas('espace', fn ($q) => $q->where('classe_id', $classe->id))->get();
        if ($fichiers->count() !== count($fichierIds)) {
            throw ValidationException::withMessages(['fichier_ids' => 'Certains fichiers n\'appartiennent pas à ce stage ou ont été supprimés.']);
        }

        $libelles = $fichiers->pluck('libelle')->unique();

        return DB::transaction(function () use ($classe, $fichiers, $libelles, $auteur) {
            $partage = new DepotPartage([
                'classe_id' => $classe->id,
                'libelle' => $libelles->count() === 1 ? $libelles->first() : null,
                'cree_par' => $auteur->id,
            ]);
            $this->nouveauToken($partage);
            $partage->save();
            $partage->fichiers()->attach($fichiers->pluck('id'));

            return $partage;
        });
    }

    /** Nouveau token et nouvelle échéance, mêmes fichiers : l'ancien lien cesse de fonctionner. */
    public function regenerer(DepotPartage $partage): DepotPartage
    {
        $this->nouveauToken($partage);
        $partage->revoque_le = null;
        $partage->save();

        return $partage;
    }

    public function revoquer(DepotPartage $partage): DepotPartage
    {
        if ($partage->revoque_le === null) {
            $partage->update(['revoque_le' => now(), 'token_chiffre' => null]);
        }

        return $partage;
    }

    /** Lien complet à copier ou envoyer ; null si le partage n'est plus actif. */
    public function lien(DepotPartage $partage): ?string
    {
        if ($partage->statut() !== DepotPartage::STATUT_ACTIF || $partage->token_chiffre === null) {
            return null;
        }

        return FrontendUrl::lien('depot/'.Crypt::decryptString($partage->token_chiffre));
    }

    /** Partage actif correspondant au token reçu (page parent) ; null si inconnu, expiré ou révoqué. */
    public function retrouver(string $token): ?DepotPartage
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $partage = DepotPartage::where('token_hash', hash('sha256', $token))->first();

        return $partage?->statut() === DepotPartage::STATUT_ACTIF ? $partage : null;
    }

    private function nouveauToken(DepotPartage $partage): void
    {
        $token = bin2hex(random_bytes(32));
        $partage->token_hash = hash('sha256', $token);
        $partage->token_chiffre = Crypt::encryptString($token);
        $partage->expire_le = now()->addDays(self::VALIDITE_JOURS);
    }
}
