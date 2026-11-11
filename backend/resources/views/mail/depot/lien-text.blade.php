{!! mb_strtoupper('Les créations de votre enfant') !!}

Bonjour,

{!! $stage ? 'Suite au stage « '.$stage.' », vous' : 'Vous' !!} pouvez télécharger {{ $nbFichiers > 1 ? 'les '.$nbFichiers.' fichiers réalisés' : 'le fichier réalisé' }} par votre enfant :

{!! $lien !!}

Lien valable jusqu'au {!! $expiration !!}. Pensez à enregistrer les fichiers avant cette date.
Ce lien est personnel : toute personne qui le possède peut télécharger ces fichiers. Ne le partagez pas.

--
{!! $ecole !!} · Email automatique envoyé à la demande de l'école.@if ($contact) Une question ? Écrivez à {!! $contact !!}.@endif
