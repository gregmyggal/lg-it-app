<?php

/** DEP-01 : dépôt de fichiers d'enfants sur les stages (voir docs/adr/0003). */
return [
    // Disque privé : jamais le disque public.
    'disque' => env('DEPOT_DISQUE', 'local'),

    'taille_max_octets' => (int) env('DEPOT_TAILLE_MAX_OCTETS', 10 * 1024 * 1024),

    // Quota par espace (stage entier ou une séance).
    'quota_espace_octets' => (int) env('DEPOT_QUOTA_ESPACE_OCTETS', 200 * 1024 * 1024),

    // Extension => types MIME réels acceptés (détection serveur, pas celle du navigateur).
    // Pas de html, svg, js, php, exécutables. Un .sb3 (Scratch) est une archive zip.
    'types' => [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'sb3' => ['application/zip', 'application/x-zip-compressed'],
        'sb2' => ['application/zip', 'application/x-zip-compressed'],
    ],
];
