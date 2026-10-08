<?php

return [
    // Nom de l'école et adresse de contact, repris dans les emails (en-tête, pied de page, Reply-To).
    'ecole' => [
        'nom' => env('ECOLE_NOM', 'Logiscool Pays Vert'),
        'contact' => env('MAIL_CONTACT'),
    ],

    // EMP-01 : valeurs d'AMORÇAGE des entités employeurs (la table `employeurs`, éditable par l'admin, fait foi à
    // l'exécution). Ne plus lire ces clés pour émettre une fiche ou une signature.
    'association' => [
        'nom' => env('ASBL_NOM', 'Code IT Bryan ! asbl'),
        'rpm' => env('ASBL_RPM', 'BE0770.479.710'),
        'banque' => env('ASBL_BANQUE', 'BE02 1431 1606 4140'),
        'adresse' => env('ASBL_ADRESSE', 'Rampe Sainte Waudru 8 – 7000 MONS'),
    ],

    // EMP-01 : coordonnées de L-IT Solutions, INCONNUES à ce jour. « À COMPLÉTER » et l'IBAN vide sont des valeurs
    // de remplacement : renseigner LIT_* dans .env avant l'amorçage, ou les saisir dans Admin > Entités employeurs.
    // Tant qu'elles sont incomplètes, aucune fiche PDF ne peut être générée au nom de cette entité.
    'lit_solutions' => [
        'nom' => env('LIT_NOM', 'L-IT Solutions'),
        'rpm' => env('LIT_RPM', 'À COMPLÉTER'),
        'banque' => env('LIT_BANQUE'),
        'adresse' => env('LIT_ADRESSE', 'À COMPLÉTER'),
    ],
];
