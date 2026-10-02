<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** TS-00 : plafonds de défraiement d'une année civile. */
class TimesheetParametre extends Model
{
    public const DEFAUT_JOURNALIER = 44.02;

    public const DEFAUT_ANNUEL = 1760.83;

    public const DEFAUT_DEPLACEMENT = 7.50;

    protected $fillable = ['annee', 'plafond_journalier_eur', 'plafond_annuel_eur', 'frais_deplacement_eur', 'updated_by'];

    protected $casts = [
        'annee' => 'integer',
        'plafond_journalier_eur' => 'float',
        'plafond_annuel_eur' => 'float',
        'frais_deplacement_eur' => 'float',
    ];
}
