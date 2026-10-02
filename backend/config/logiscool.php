<?php

/** Coordonnées de l'ASBL, imprimées en pied de la fiche de défraiement PDF. */
return [
    'association' => [
        'nom' => env('ASBL_NOM', 'Code IT Bryan ! asbl'),
        'rpm' => env('ASBL_RPM', 'BE0770.479.710'),
        'banque' => env('ASBL_BANQUE', 'BE02 1431 1606 4140'),
        'adresse' => env('ASBL_ADRESSE', 'Rampe Sainte Waudru 8 – 7000 MONS'),
    ],
];
