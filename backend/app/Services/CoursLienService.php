<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\ClasseLien;
use App\Models\ClasseLienVersion;
use App\Models\Cours;
use App\Models\CoursRessource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Liens d'un cours (CLS-01 T4) : généraux (`seance_numero` NULL) ou par séance (1..14), partagés par toutes les
 * classes du cours. Chaque changement produit une version append-only ; supprimer = archiver ; restaurer = nouvelle version.
 */
class CoursLienService
{
    /** Un lot de déplacements du même auteur, dans la même portée, en moins de N minutes = une seule version. */
    private const FENETRE_LOT_ORDRE_MINUTES = 5;

    /** Types de ressource historiques → types de lien. */
    private const TYPES_RESSOURCE = ['video' => 'video', 'outil' => 'outil', 'document' => 'document', 'jeu' => 'jeu'];

    /** Liens actifs du cours : généraux d'abord, puis par séance, chacun dans son ordre. */
    public function liste(Cours $cours): Collection
    {
        return ClasseLien::query()
            ->where('parent_type', ClasseLien::PARENT_COURS)->where('parent_id', $cours->id)
            ->with('auteurModification')
            ->orderByRaw('seance_numero IS NOT NULL')->orderBy('seance_numero')->orderBy('ordre')->orderBy('id')
            ->get();
    }

    /** État métier d'un lien (sert à l'historique et à la détection de modification concurrente). */
    public function etat(ClasseLien $lien): array
    {
        return [
            'titre' => $lien->titre,
            'url' => $lien->url,
            'description' => $lien->description,
            'type' => $lien->theme,
            'seance_numero' => $lien->seance_numero,
            'ordre' => $lien->ordre,
        ];
    }

    /**
     * @param  array{titre: string, url: string, description?: ?string, type?: ?string, seance_numero?: ?int}  $data
     */
    public function creer(Cours $cours, User $user, array $data): ClasseLien
    {
        return DB::transaction(function () use ($cours, $user, $data) {
            $seance = $data['seance_numero'] ?? null;
            $lien = new ClasseLien([
                'titre' => $data['titre'],
                'url' => $data['url'],
                'description' => $data['description'] ?? null,
                'theme' => $data['type'] ?? null,
                'seance_numero' => $seance,
                'ordre' => $this->prochainOrdre($cours, $seance),
                'version' => 1,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $lien->parent()->associate($cours)->save();

            $this->versionner($lien, $user, ClasseLienVersion::ACTION_CREATION, null, $this->etat($lien));

            return $lien->load('auteurModification');
        });
    }

    /**
     * @param  array<string, mixed>  $data  champs modifiés (titre, url, description, type, seance_numero)
     *
     * @throws RegleMetierException 409 si la version attendue est dépassée (rien n'est écrasé en silence)
     */
    public function modifier(ClasseLien $lien, User $user, array $data, int $versionAttendue): ClasseLien
    {
        return DB::transaction(function () use ($lien, $user, $data, $versionAttendue) {
            $lien = ClasseLien::whereKey($lien->id)->lockForUpdate()->firstOrFail();

            if ($lien->version !== $versionAttendue) {
                $auteur = $lien->auteurModification?->name;
                throw RegleMetierException::conflit(
                    'Ce lien a été modifié'.($auteur ? " par {$auteur}" : '').' depuis que vous l\'avez ouvert. Reprenez sa version actuelle ou enregistrez la vôtre.',
                    ['lien' => $this->etat($lien) + ['id' => $lien->id, 'version' => $lien->version]]
                );
            }

            $avant = $this->etat($lien);
            $this->appliquer($lien, $data);
            $changementPortee = $lien->isDirty('seance_numero');
            if ($changementPortee) {
                $lien->ordre = $this->prochainOrdre($lien->parent, $lien->seance_numero, $lien->id);
            }
            if (! $lien->isDirty()) {
                return $lien; // aucun changement : pas de version
            }

            $lien->version++;
            $lien->updated_by = $user->id;
            $lien->save();

            $this->versionner($lien, $user, $changementPortee ? ClasseLienVersion::ACTION_PORTEE : ClasseLienVersion::ACTION_MODIFICATION, $avant, $this->etat($lien));

            return $lien->load('auteurModification');
        });
    }

    /** « Supprimer » = archiver : le lien disparaît partout mais reste restaurable 6 mois. */
    public function archiver(ClasseLien $lien, User $user): ClasseLienVersion
    {
        return DB::transaction(function () use ($lien, $user) {
            $lien = ClasseLien::whereKey($lien->id)->lockForUpdate()->firstOrFail();
            $avant = $this->etat($lien);
            $lien->version++;
            $lien->updated_by = $user->id;
            $lien->save();
            $lien->delete();

            return $this->versionner($lien, $user, ClasseLienVersion::ACTION_ARCHIVAGE, $avant, null);
        });
    }

    /**
     * Réordonne UNE portée (généraux ou une séance). `ids` doit être exactement l'ensemble de ses liens actifs.
     *
     * @param  list<int>  $ids
     * @return Collection<int, ClasseLien>
     */
    public function ordonner(Cours $cours, User $user, ?int $seance, array $ids): Collection
    {
        return DB::transaction(function () use ($cours, $user, $seance, $ids) {
            $portee = $this->portee($cours, $seance)->lockForUpdate()->orderBy('ordre')->orderBy('id')->get();

            if ($portee->pluck('id')->sort()->values()->all() !== collect($ids)->sort()->values()->all()) {
                throw RegleMetierException::invalide('La liste ne correspond pas aux liens de cette portée. Rechargez la page.', ['ids' => ['Liste de liens invalide pour cette portée.']]);
            }

            [$avant, $apres] = $this->appliquerOrdre($portee, $ids, $user);

            if ($avant !== $apres) {
                $this->versionnerOrdre($cours, $user, $seance, $avant, $apres);
            }

            return $this->liste($cours);
        });
    }

    /**
     * Restaure l'état précédant une version (défait la modification, annule l'archivage, rétablit l'ordre).
     * Crée une NOUVELLE version « restauration » : l'historique n'est jamais réécrit.
     *
     * @throws RegleMetierException 410 version purgée ; 422 création ; 409 modifié depuis (sans confirmation)
     */
    public function restaurer(ClasseLienVersion $version, User $user, bool $confirmer): ClasseLien|Collection
    {
        if ($version->estExpiree()) {
            throw new RegleMetierException('Cette version date de plus de 6 mois : elle n\'est plus restaurable.', 410);
        }
        if ($version->action === ClasseLienVersion::ACTION_CREATION) {
            throw RegleMetierException::invalide('Une création ne se restaure pas : archivez le lien à la place.');
        }

        return DB::transaction(function () use ($version, $user, $confirmer) {
            $apres = $version->apres;
            if (is_array($apres) && array_key_exists('liste', $apres)) {
                return $this->restaurerOrdre($version, $user, $confirmer);
            }

            $lien = ClasseLien::withTrashed()->whereKey($version->classe_lien_id)->lockForUpdate()->firstOrFail();

            if ($version->action === ClasseLienVersion::ACTION_ARCHIVAGE) {
                if (! $lien->trashed()) {
                    throw RegleMetierException::conflit('Ce lien n\'est plus archivé : il a déjà été restauré.');
                }

                return $this->desarchiver($lien, $user, $version);
            }

            // modification / portée / restauration d'un état
            $avantVersion = $version->avant;
            if ($lien->trashed() && ! ($version->avant['archive'] ?? false)) {
                throw RegleMetierException::conflit('Ce lien est archivé : annulez d\'abord sa suppression.');
            }
            if (($version->avant['archive'] ?? false) === true) {
                return $this->rearchiver($lien, $user, $version);
            }

            $courant = $this->etat($lien);
            $this->exigerConfirmationSiModifie($courant, $apres, $confirmer, $lien);

            $this->appliquer($lien, collect($avantVersion)->except(['ordre', 'archive'])->all());
            if ($lien->isDirty('seance_numero')) {
                $lien->ordre = $this->prochainOrdre($lien->parent, $lien->seance_numero, $lien->id);
            }
            $lien->version++;
            $lien->updated_by = $user->id;
            $lien->save();

            $this->versionner($lien, $user, ClasseLienVersion::ACTION_RESTAURATION, $courant, $this->etat($lien), $version->id);

            return $lien->load('auteurModification');
        });
    }

    /**
     * Reprend les anciennes ressources du cours en liens généraux (action humaine, versionnée).
     *
     * @return int nombre de liens créés
     */
    public function reprendreRessources(Cours $cours, User $user): int
    {
        return DB::transaction(function () use ($cours, $user) {
            $ressources = CoursRessource::where('cours_id', $cours->id)->whereNull('repris_at')->orderBy('ordre')->orderBy('id')->lockForUpdate()->get();

            foreach ($ressources as $r) {
                $this->creer($cours, $user, [
                    'titre' => $r->titre_ressource,
                    'url' => $r->url_ressource,
                    'type' => self::TYPES_RESSOURCE[$r->type_ressource] ?? null,
                ]);
                $r->update(['repris_at' => now()]);
            }

            return $ressources->count();
        });
    }

    /** Nombre d'anciennes ressources pas encore reprises. */
    public function ressourcesNonReprises(Cours $cours): int
    {
        return CoursRessource::where('cours_id', $cours->id)->whereNull('repris_at')->count();
    }

    // ---------------------------------------------------------------------------------------------

    private function portee(Cours $cours, ?int $seance)
    {
        return ClasseLien::query()
            ->where('parent_type', ClasseLien::PARENT_COURS)->where('parent_id', $cours->id)
            ->when($seance === null, fn ($q) => $q->whereNull('seance_numero'), fn ($q) => $q->where('seance_numero', $seance));
    }

    private function prochainOrdre(Cours|Model $cours, ?int $seance, ?int $sauf = null): int
    {
        return 1 + (int) $this->portee($cours, $seance)->when($sauf, fn ($q) => $q->where('id', '!=', $sauf))->max('ordre');
    }

    /** @param array<string, mixed> $data */
    private function appliquer(ClasseLien $lien, array $data): void
    {
        foreach (['titre', 'url', 'description'] as $champ) {
            if (array_key_exists($champ, $data)) {
                $lien->{$champ} = $data[$champ];
            }
        }
        if (array_key_exists('type', $data)) {
            $lien->theme = $data['type']; // facultatif : null = aucun type
        }
        if (array_key_exists('seance_numero', $data)) {
            $lien->seance_numero = $data['seance_numero'];
        }
    }

    /**
     * Applique un nouvel ordre à une portée (sans versionner).
     *
     * @param  Collection<int, ClasseLien>  $portee
     * @param  list<int>  $ids
     * @return array{0: list<array{id: int, titre: string}>, 1: list<array{id: int, titre: string}>} avant, après
     */
    private function appliquerOrdre(Collection $portee, array $ids, User $user): array
    {
        $avant = $portee->map(fn (ClasseLien $l) => ['id' => $l->id, 'titre' => $l->titre])->values()->all();
        foreach ($ids as $index => $id) {
            $lien = $portee->firstWhere('id', $id);
            if ($lien->ordre !== $index + 1) {
                $lien->ordre = $index + 1;
                $lien->version++;
                $lien->updated_by = $user->id;
                $lien->save();
            }
        }
        $apres = collect($ids)->map(fn ($id) => ['id' => $id, 'titre' => $portee->firstWhere('id', $id)->titre])->all();

        return [$avant, $apres];
    }

    private function versionner(ClasseLien $lien, User $user, string $action, ?array $avant, ?array $apres, ?int $restaureDepuis = null): ClasseLienVersion
    {
        return ClasseLienVersion::create([
            'classe_lien_id' => $lien->id,
            'parent_type' => $lien->parent_type,
            'parent_id' => $lien->parent_id,
            'action' => $action,
            'avant' => $avant,
            'apres' => $apres,
            'user_id' => $user->id,
            'restaure_depuis_id' => $restaureDepuis,
        ]);
    }

    /** Un lot de déplacements (même auteur, même portée, < 5 min) = une seule version : on met à jour la dernière. */
    private function versionnerOrdre(Cours $cours, User $user, ?int $seance, array $avant, array $apres): void
    {
        $derniere = ClasseLienVersion::where('parent_type', ClasseLien::PARENT_COURS)->where('parent_id', $cours->id)
            ->where('action', ClasseLienVersion::ACTION_ORDRE)->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(self::FENETRE_LOT_ORDRE_MINUTES))
            ->latest('id')->first();

        if ($derniere && ($derniere->apres['seance_numero'] ?? null) === $seance
            && collect($derniere->apres['liste'])->pluck('id')->sort()->values()->all() === collect($apres)->pluck('id')->sort()->values()->all()) {
            $derniere->update(['apres' => ['seance_numero' => $seance, 'liste' => $apres]]);

            return;
        }

        ClasseLienVersion::create([
            'classe_lien_id' => $apres[0]['id'],
            'parent_type' => ClasseLien::PARENT_COURS,
            'parent_id' => $cours->id,
            'action' => ClasseLienVersion::ACTION_ORDRE,
            'avant' => ['seance_numero' => $seance, 'liste' => $avant],
            'apres' => ['seance_numero' => $seance, 'liste' => $apres],
            'user_id' => $user->id,
        ]);
    }

    private function restaurerOrdre(ClasseLienVersion $version, User $user, bool $confirmer): Collection
    {
        $cours = Cours::findOrFail($version->parent_id);
        $seance = $version->apres['seance_numero'];
        $actuelle = $this->portee($cours, $seance)->lockForUpdate()->orderBy('ordre')->orderBy('id')->get();

        $ordreApres = collect($version->apres['liste'])->pluck('id')->all();
        if (! $confirmer && $actuelle->pluck('id')->all() !== $ordreApres) {
            throw RegleMetierException::conflit('L\'ordre de cette portée a changé depuis. Confirmez pour rétablir l\'ordre précédent.', ['modifie_depuis' => true]);
        }

        $souhaite = collect($version->avant['liste'])->pluck('id')->filter(fn ($id) => $actuelle->contains('id', $id))->values();
        $ordreFinal = $souhaite->merge($actuelle->pluck('id')->diff($souhaite))->values()->all();
        [$avant, $apres] = $this->appliquerOrdre($actuelle, $ordreFinal, $user);

        if ($avant !== $apres) {
            // Version propre (jamais fusionnée avec un lot de déplacements) : l'historique n'est pas réécrit.
            ClasseLienVersion::create([
                'classe_lien_id' => $apres[0]['id'],
                'parent_type' => ClasseLien::PARENT_COURS,
                'parent_id' => $cours->id,
                'action' => ClasseLienVersion::ACTION_RESTAURATION,
                'avant' => ['seance_numero' => $seance, 'liste' => $avant],
                'apres' => ['seance_numero' => $seance, 'liste' => $apres],
                'user_id' => $user->id,
                'restaure_depuis_id' => $version->id,
            ]);
        }

        return $this->liste($cours);
    }

    private function desarchiver(ClasseLien $lien, User $user, ClasseLienVersion $version): ClasseLien
    {
        $lien->restore();
        $lien->ordre = $this->prochainOrdre($lien->parent, $lien->seance_numero, $lien->id);
        $lien->version++;
        $lien->updated_by = $user->id;
        $lien->save();

        // `avant.archive` : défaire cette restauration = ré-archiver.
        $this->versionner($lien, $user, ClasseLienVersion::ACTION_RESTAURATION, ['archive' => true] + ($version->avant ?? []), $this->etat($lien), $version->id);

        return $lien->load('auteurModification');
    }

    private function rearchiver(ClasseLien $lien, User $user, ClasseLienVersion $version): ClasseLien
    {
        $avant = $this->etat($lien);
        $lien->version++;
        $lien->updated_by = $user->id;
        $lien->save();
        $lien->delete();
        $this->versionner($lien, $user, ClasseLienVersion::ACTION_RESTAURATION, $avant, ['archive' => true] + $avant, $version->id);

        return $lien;
    }

    /** @param array<string, mixed> $courant */
    private function exigerConfirmationSiModifie(array $courant, ?array $apres, bool $confirmer, ClasseLien $lien): void
    {
        $sansOrdre = fn (?array $e) => collect($e ?? [])->except(['ordre', 'archive'])->all();

        // `==` : la colonne JSON MySQL réordonne les clés, seule l'égalité de contenu compte.
        if (! $confirmer && $sansOrdre($courant) != $sansOrdre($apres)) {
            throw RegleMetierException::conflit(
                'Ce lien a été modifié depuis cette version. Confirmez pour rétablir l\'état précédent ; la version actuelle reste dans l\'historique.',
                ['modifie_depuis' => $courant + ['id' => $lien->id, 'version' => $lien->version]]
            );
        }
    }
}
