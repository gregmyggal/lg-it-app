Bonjour {!! $nom !!},

Un compte {!! $role !!} vient d'être créé pour vous sur la plateforme Logiscool Pays Vert@if ($role === 'professeur'), qui vous permet de consulter vos classes et d'encoder vos heures@endif.
Pour l'activer, choisissez votre mot de passe :

{!! $lien !!}

Ce lien est valable {!! $validite !!} et ne peut être utilisé qu'une seule fois.@if ($role === 'professeur') Ensuite, connectez-vous avec cette adresse email.@endif
Si vous n'attendiez pas cet email, ignorez-le : aucun compte n'est actif sans votre action.@if ($role === 'professeur')
Une question ? Contactez la direction de votre centre.
@endif
