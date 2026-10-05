<?php

return [
    // SIG-01 : clé privée Ed25519 (base64 de la clé secrète de 64 octets) qui scelle chaque signature de mois.
    // Générer avec `php artisan signature:cle`. Sans clé, une clé dérivée d'APP_KEY est utilisée (identifiant « app »).
    'cle_privee' => env('SIGNATURE_CLE_PRIVEE'),
    'cle_id' => env('SIGNATURE_CLE_ID', 'cle-1'),

    // Anciennes clés publiques (base64), pour vérifier les sceaux émis avant une rotation : ['cle-1' => '...'].
    'cles_publiques' => array_filter(json_decode((string) env('SIGNATURE_CLES_PUBLIQUES', '{}'), true) ?: []),

    // Durée de conservation de l'IP et de l'appareil (RGPD), alignée sur les pièces comptables.
    'retention_annees' => 7,
];
