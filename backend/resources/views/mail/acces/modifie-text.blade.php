{!! mb_strtoupper($activation ? "C'est prêt !" : 'Mot de passe modifié') !!}

Bonjour {!! $prenom !!},

Le mot de passe de votre compte {!! $email !!} a été enregistré le {!! $date !!}.

Me connecter : {!! $urlConnexion !!}

Par sécurité, vous avez été déconnecté(e) de vos autres appareils.

Ce n'était pas vous ? Choisissez tout de suite un nouveau mot de passe via « Mot de passe oublié » ({!! $urlOubli !!}), puis prévenez {!! $contact ?: 'la direction de votre école' !!}.

--
{!! $ecole !!} · Email automatique de sécurité envoyé à {!! $email !!}.@if ($contact) Une question ? Écrivez à {!! $contact !!}.@endif

