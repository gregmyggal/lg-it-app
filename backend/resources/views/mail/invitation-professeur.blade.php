Bonjour {{ $prenom }},

@if ($motif === 'reactivation')
Votre accès à la plateforme a été réactivé.
@elseif ($motif === 'reinitialisation')
Votre mot de passe a été réinitialisé.
@else
Votre compte sur la plateforme a été créé.
@endif

Identifiant : {{ $loginEmail }}
Mot de passe provisoire : {{ $motDePasse }}

Vous devrez choisir un nouveau mot de passe dès votre première connexion.
Si vous n'êtes pas concerné(e), contactez la direction.
