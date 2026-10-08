<?php

namespace App\Models;

use App\Rules\Iban;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EMP-01 : entité qui emploie et paie un animateur pour un mois donné (ASBL ou L-IT Solutions).
 * Le compte bancaire est chiffré au repos. Une entité utilisée ne se supprime pas : elle se désactive.
 */
class Employeur extends Model
{
    use HasFactory;

    public const CODE_ASBL = 'asbl';

    public const CODE_LIT = 'lit_solutions';

    /** Valeur de remplacement tant qu'une coordonnée est inconnue (jamais une coordonnée inventée). */
    public const A_COMPLETER = 'À COMPLÉTER';

    protected $fillable = ['code', 'nom', 'rpm', 'compte_bancaire', 'adresse', 'par_defaut', 'actif', 'couleur_badge'];

    protected $casts = [
        'compte_bancaire' => 'encrypted',
        'par_defaut' => 'boolean',
        'actif' => 'boolean',
    ];

    public function moisLies(): HasMany
    {
        return $this->hasMany(ProfesseurEmployeurMois::class);
    }

    public static function parDefaut(): self
    {
        return self::where('par_defaut', true)->first() ?? self::where('code', self::CODE_ASBL)->firstOrFail();
    }

    /**
     * Crée, si elles manquent, les deux entités d'amorçage depuis la configuration (idempotent : n'écrase jamais
     * une entité existante, éditée par l'admin).
     */
    public static function amorcer(): void
    {
        $asbl = config('logiscool.association');
        $lit = config('logiscool.lit_solutions');
        $creer = fn (string $code, array $c, bool $defaut, string $couleur) => self::firstOrCreate(['code' => $code], [
            'nom' => $c['nom'],
            'rpm' => $c['rpm'] ?: self::A_COMPLETER,
            'compte_bancaire' => Iban::normaliser($c['banque'] ?? null),
            'adresse' => $c['adresse'] ?: self::A_COMPLETER,
            'par_defaut' => $defaut,
            'actif' => true,
            'couleur_badge' => $couleur,
        ]);
        $creer(self::CODE_ASBL, $asbl, true, 'bleu');
        $creer(self::CODE_LIT, $lit, false, 'violet');
    }

    /** Coordonnées complètes (RPM et adresse renseignés, IBAN valide) : condition pour émettre une fiche. */
    public function coordonneesCompletes(): bool
    {
        $iban = Iban::normaliser($this->compte_bancaire);

        return filled($this->nom)
            && ! str_contains((string) $this->rpm, self::A_COMPLETER) && filled($this->rpm)
            && ! str_contains((string) $this->adresse, self::A_COMPLETER) && filled($this->adresse)
            && $iban !== null && Iban::estValide($iban);
    }

    /** IBAN par groupes de 4 (« BE02 1431 1606 4140 »), null si absent. */
    public function compteFormate(): ?string
    {
        $iban = Iban::normaliser($this->compte_bancaire);

        return $iban === null ? null : trim(chunk_split($iban, 4, ' '));
    }

    /** Forme légère (sans coordonnées bancaires) embarquée dans les écrans mensuels. */
    public function resume(): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'nom' => $this->nom, 'couleur_badge' => $this->couleur_badge, 'actif' => $this->actif, 'coordonnees_completes' => $this->coordonneesCompletes()];
    }

    /** Photo des coordonnées copiée sur la fiche PDF au moment de sa génération (RG-8). */
    public function snapshot(): array
    {
        return ['code' => $this->code, 'nom' => $this->nom, 'rpm' => $this->rpm, 'compte_bancaire' => $this->compteFormate(), 'adresse' => $this->adresse];
    }
}
