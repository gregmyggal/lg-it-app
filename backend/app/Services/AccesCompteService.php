<?php

namespace App\Services;

use App\Mail\InvitationCompteMail;
use App\Mail\MotDePasseModifieMail;
use App\Mail\ReinitialisationMotDePasseMail;
use App\Models\AccesToken;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/** ADMIN-02 : invitation et réinitialisation du mot de passe par lien à usage unique. */
class AccesCompteService
{
    public const INVITATION = 'invitation';

    public const REINITIALISATION = 'reinitialisation';

    public const DUREE_ADMIN_MINUTES = 72 * 60;

    public const DUREE_LIBRE_SERVICE_MINUTES = 60;

    /** Compte autorisé à recevoir un lien (un compte désactivé ne se « réactive » pas seul). */
    public function estActif(User $user): bool
    {
        if ($user->isProfesseur()) {
            return $user->professeur?->statut === 'actif';
        }

        return $user->statut === 'actif';
    }

    /** Statut d'accès affiché (calculé ici : le backend est la source de vérité). */
    public function statutAcces(User $user): ?string
    {
        if (! $this->estActif($user)) {
            return null;
        }
        if ($user->mot_de_passe_defini_le) {
            return 'mot_de_passe_defini';
        }
        if ($user->must_change_password) {
            return 'mot_de_passe_provisoire';
        }
        if (! $user->invitation_envoyee_le) {
            return 'invitation_non_envoyee';
        }

        $token = $this->dernierToken($user);

        return (! $token || $token->expire_le->isPast()) ? 'invitation_expiree' : 'invitation_en_attente';
    }

    /** @return array{statut: ?string, motif: ?string, invitation_envoyee_le: ?string, invitation_expire_le: ?string, mot_de_passe_defini_le: ?string, a_relancer: bool} */
    public function resumeAcces(User $user): array
    {
        $statut = $this->statutAcces($user);
        $token = $statut === null ? null : $this->dernierToken($user);

        return [
            'statut' => $statut,
            'invitation_envoyee_le' => $user->invitation_envoyee_le?->toIso8601String(),
            'invitation_expire_le' => in_array($statut, ['invitation_en_attente', 'invitation_expiree'], true)
                ? $token?->expire_le->toIso8601String() : null,
            'mot_de_passe_defini_le' => $user->mot_de_passe_defini_le?->toIso8601String(),
            'motif' => $statut === 'invitation_non_envoyee' ? ($user->invitation_echec_le ? 'echec' : 'volontaire') : null,
            'a_relancer' => $this->aRelancer($statut, $user),
        ];
    }

    /** À relancer : envoi en échec, invitation expirée, ou en attente depuis plus de 3 jours (pas un accès volontairement non envoyé). */
    private function aRelancer(?string $statut, User $user): bool
    {
        return match ($statut) {
            'invitation_non_envoyee' => $user->invitation_echec_le !== null,
            'invitation_expiree' => true,
            'invitation_en_attente' => $user->invitation_envoyee_le?->lt(now()->subDays(3)) ?? false,
            default => false,
        };
    }

    /** Type de lien adapté à la situation du compte. */
    public function typePour(User $user): string
    {
        return in_array($this->statutAcces($user), ['mot_de_passe_defini', 'mot_de_passe_provisoire'], true)
            ? self::REINITIALISATION
            : self::INVITATION;
    }

    /** Secondes à attendre avant un nouvel envoi par un admin (0 = autorisé). */
    public function attenteEnvoi(User $user): int
    {
        $cle = $this->cleEnvoi($user);

        return RateLimiter::tooManyAttempts($cle, 1) ? RateLimiter::availableIn($cle) : 0;
    }

    /**
     * Émet un lien (annule les précédents) et l'envoie par email de façon synchrone.
     * Un échec d'envoi ne lève pas d'exception : le lien est alors renvoyé pour être transmis à la main.
     *
     * @return array{envoye: bool, lien: ?string}
     */
    public function envoyer(User $user, string $type, int $dureeMinutes, bool $parDirection = false): array
    {
        $lien = $this->emettre($user, $type, $dureeMinutes);

        if ($type === self::INVITATION) {
            $user->forceFill(['invitation_envoyee_le' => null])->save();
        }

        try {
            $mail = $type === self::INVITATION
                ? new InvitationCompteMail($user->name, $this->libelleRole($user), $lien, $this->libelleDuree($dureeMinutes))
                : new ReinitialisationMotDePasseMail($user->name, $lien, $this->libelleDuree($dureeMinutes), $parDirection);
            Mail::to($user->email)->send($mail);
        } catch (Throwable $e) {
            report($e);
            Log::warning('acces_compte: échec d\'envoi', ['user_id' => $user->id, 'type' => $type]);
            if ($type === self::INVITATION) {
                $user->forceFill(['invitation_echec_le' => now()])->save();
            }

            return ['envoye' => false, 'lien' => $lien];
        }

        if ($type === self::INVITATION) {
            $user->forceFill(['invitation_envoyee_le' => now(), 'invitation_echec_le' => null])->save();
        }
        RateLimiter::hit($this->cleEnvoi($user), 60);
        Log::info('acces_compte: email envoyé', ['user_id' => $user->id, 'type' => $type]);

        return ['envoye' => true, 'lien' => null];
    }

    public const LOT_MAX = 25;

    /**
     * ADMIN-05 : envoie l'invitation à chaque compte « accès non envoyé » (séquentiel, un compte = une ligne de résultat).
     * Les comptes déjà invités, désactivés ou limités (1 envoi/min) sont rapportés sans double envoi.
     *
     * @param  iterable<User>  $users
     * @return list<array{id: int, nom: string, email: string, resultat: string, motif: ?string}>
     */
    public function envoyerEnLot(iterable $users): array
    {
        $lignes = [];
        foreach ($users as $user) {
            $ligne = ['id' => $user->id, 'nom' => $user->name, 'email' => $user->email, 'resultat' => 'ignore', 'motif' => null];

            if ($this->statutAcces($user) !== 'invitation_non_envoyee') {
                $ligne['motif'] = $this->estActif($user) ? 'Déjà invité' : 'Compte désactivé';
            } elseif (($attente = $this->attenteEnvoi($user)) > 0) {
                $ligne['resultat'] = 'echec';
                $ligne['motif'] = 'Un email vient d\'être envoyé. Réessayez dans '.$attente.' seconde'.($attente > 1 ? 's' : '').'.';
            } elseif ($this->envoyer($user, self::INVITATION, self::DUREE_ADMIN_MINUTES, parDirection: true)['envoye']) {
                $ligne['resultat'] = 'envoye';
            } else {
                $ligne['resultat'] = 'echec';
                $ligne['motif'] = 'Email non envoyé';
            }

            $lignes[] = $ligne;
        }

        return $lignes;
    }

    /** Lien à transmettre soi-même (repli quand l'email n'arrive pas). */
    public function genererLienManuel(User $user, int $parUserId): string
    {
        $type = $this->typePour($user);
        $lien = $this->emettre($user, $type, self::DUREE_ADMIN_MINUTES);

        if ($type === self::INVITATION) {
            $user->forceFill(['invitation_envoyee_le' => now(), 'invitation_echec_le' => null])->save();
        }
        Log::info('acces_compte: lien généré manuellement', ['user_id' => $user->id, 'type' => $type, 'par' => $parUserId]);

        return $lien;
    }

    /** Self-service « Mot de passe oublié » : silencieux si le compte est inconnu, désactivé ou déjà sollicité. */
    public function demanderOubli(string $email): void
    {
        $user = User::where('email', $email)->first();
        if (! $user || ! $this->estActif($user)) {
            return;
        }

        $cle = 'acces-oubli:'.$user->id;
        if (RateLimiter::tooManyAttempts($cle, 1)) {
            return;
        }
        RateLimiter::hit($cle, 60);

        $this->envoyer($user, self::REINITIALISATION, self::DUREE_LIBRE_SERVICE_MINUTES);
    }

    public function lienValide(string $email, string $token): bool
    {
        return $this->trouver($email, $token) !== null;
    }

    /** Consomme le lien : définit le mot de passe, met fin aux sessions, prévient l'utilisateur. */
    public function definirMotDePasse(string $email, string $token, string $motDePasse): bool
    {
        $acces = $this->trouver($email, $token);
        if (! $acces) {
            return false;
        }

        $user = $acces->user;

        DB::transaction(function () use ($user, $motDePasse) {
            $user->forceFill([
                'password' => $motDePasse,
                'must_change_password' => false,
                'mot_de_passe_defini_le' => now(),
            ])->save();
            AccesToken::where('user_id', $user->id)->delete();
            $user->tokens()->delete();
        });

        Log::info('acces_compte: mot de passe défini', ['user_id' => $user->id]);

        try {
            Mail::to($user->email)->send(new MotDePasseModifieMail($user->name));
        } catch (Throwable $e) {
            report($e);
        }

        return true;
    }

    /** Mot de passe aléatoire inutilisable : le compte n'est accessible que via un lien. */
    public function motDePasseInutilisable(): string
    {
        return Str::random(40);
    }

    /** La désactivation annule les liens en cours. */
    public function annulerLiens(User $user): void
    {
        AccesToken::where('user_id', $user->id)->delete();
    }

    private function emettre(User $user, string $type, int $dureeMinutes): string
    {
        $token = Str::random(64);

        DB::transaction(function () use ($user, $type, $dureeMinutes, $token) {
            AccesToken::where('user_id', $user->id)->delete();
            AccesToken::create([
                'user_id' => $user->id,
                'type' => $type,
                'token_hash' => hash('sha256', $token),
                'expire_le' => now()->addMinutes($dureeMinutes),
            ]);
        });

        // Token et email en fragment d'URL : absents des journaux serveur et de l'en-tête Referer.
        return rtrim(config('app.frontend_url'), '/').'/definir-mot-de-passe#token='.$token.'&email='.rawurlencode($user->email);
    }

    private function trouver(string $email, string $token): ?AccesToken
    {
        $user = User::where('email', $email)->first();
        if (! $user || ! $this->estActif($user)) {
            return null;
        }

        $acces = AccesToken::with('user')
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return ($acces && ! $acces->expire_le->isPast()) ? $acces : null;
    }

    /** Utilise la relation préchargée (liste) pour éviter une requête par compte. */
    private function dernierToken(User $user): ?AccesToken
    {
        if ($user->relationLoaded('accesTokens')) {
            return $user->accesTokens->sortByDesc('id')->first();
        }

        return AccesToken::where('user_id', $user->id)->latest('id')->first();
    }

    private function cleEnvoi(User $user): string
    {
        return 'acces-envoi:'.$user->id;
    }

    private function libelleRole(User $user): string
    {
        return match ($user->role) {
            'admin' => 'administrateur',
            'directeur' => 'directeur',
            'professeur' => 'professeur',
            default => $user->role,
        };
    }

    private function libelleDuree(int $minutes): string
    {
        return ($minutes >= 120 && $minutes % 60 === 0) ? ($minutes / 60).' h' : $minutes.' minutes';
    }
}
