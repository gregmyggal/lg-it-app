<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** TS-00 : plafonds de défraiement d'une année civile. */
class TimesheetParametre extends Model
{
    public const DEFAUT_JOURNALIER = 44.02;

    public const DEFAUT_ANNUEL = 1760.83;

    public const DEFAUT_DEPLACEMENT = 7.50;

    /** Heures défrayées par séance (cours + préparation) et durée de séance proposée à la création d'une classe. */
    public const DEFAUT_HEURES_DEFRAYABLES = 2.0;

    public const DEFAUT_DUREE_SEANCE = 1.5;

    protected $fillable = ['annee', 'plafond_journalier_eur', 'plafond_annuel_eur', 'frais_deplacement_eur', 'heures_defrayables', 'duree_seance_defaut', 'updated_by'];

    protected $casts = [
        'annee' => 'integer',
        'plafond_journalier_eur' => 'float',
        'plafond_annuel_eur' => 'float',
        'frais_deplacement_eur' => 'float',
        'heures_defrayables' => 'float',
        'duree_seance_defaut' => 'float',
    ];
}
