{!! mb_strtoupper($parDirection ? 'Choisissez un nouveau mot de passe' : 'Mot de passe oublié ?') !!}

Bonjour {!! $prenom !!},

@if ($parDirection)
{!! $par ? $par.' (direction)' : 'La direction de votre école' !!} vous a envoyé un lien pour choisir un nouveau mot de passe sur {!! $ecole !!}.
@else
Vous avez demandé à réinitialiser le mot de passe de votre compte {!! $ecole !!}.
@endif

Choisir un nouveau mot de passe : {!! $lien !!}

Identifiant : {!! $email !!}
@if ($parDirection)
Lien valable jusqu'au {!! $expiration !!}, une seule fois.
Tant que vous n'avez pas choisi de nouveau mot de passe, l'ancien continue de fonctionner.
@else
Valable {!! $validite !!}, jusqu'à {!! $expiration !!}, une seule fois.
@endif

Le lien a expiré ? Demandez-en un nouveau sur la page « Mot de passe oublié » : {!! $urlOubli !!}

Vous n'avez rien demandé ? Ignorez cet email : votre mot de passe actuel ne change pas.

--
{!! $ecole !!} · Email automatique envoyé à {!! $email !!} suite à une demande sur la plateforme.@if ($contact) Une question ? Écrivez à {!! $contact !!}.@endif

