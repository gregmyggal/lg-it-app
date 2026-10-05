{!! mb_strtoupper($role === 'professeur' ? "Bienvenue dans l'équipe !" : 'Votre accès est prêt') !!}

Bonjour {!! $prenom !!},

@if ($role === 'professeur')
{!! $par ?? 'La direction de votre école' !!} a créé votre compte sur {!! $ecole !!}, l'espace où vous retrouvez vos classes et vos séances, et où vous encodez vos heures.
@else
{!! $par ?? 'La direction de votre école' !!} vous a donné un accès {!! $role !!} à {!! $ecole !!} : classes, calendrier, professeurs et validation des heures.
@endif

Choisir mon mot de passe : {!! $lien !!}

Identifiant : {!! $email !!}
Lien valable jusqu'au {!! $expiration !!}, utilisable une seule fois.

Le lien a expiré ? Ouvrez-le quand même : la page vous proposera d'en recevoir un nouveau. Ensuite, connectez-vous sur {!! $urlConnexion !!}

Vous ne vous attendiez pas à cet email ? Ignorez-le : le compte reste inactif tant qu'aucun mot de passe n'est choisi. Nous ne vous demanderons jamais votre mot de passe par email.

--
{!! $ecole !!} · Email automatique envoyé à {!! $email !!} car un compte a été créé à votre nom.@if ($contact) Une question ? Écrivez à {!! $contact !!}.@endif

