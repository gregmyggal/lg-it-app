<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetPdf extends Model
{
    public $timestamps = false;

    protected $fillable = ['professeur_id', 'annee', 'mois', 'version', 'chemin', 'total_eur', 'generated_by', 'generated_at'];

    protected $casts = ['generated_at' => 'datetime', 'total_eur' => 'float'];

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }

    /** Nom de fichier proposé au téléchargement, sur le modèle « 202606 Note de frais_Prénom Nom.pdf ». */
    public function nomFichier(): string
    {
        $p = $this->professeur;

        return sprintf('%04d%02d Fiche de défraiement_%s.pdf', $this->annee, $this->mois, trim($p->prenom.' '.$p->nom));
    }
}
