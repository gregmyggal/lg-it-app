@extends('mail.acces.layout')
@section('preheader'){{ $par ?? 'La direction de votre école' }} vous a créé un compte. Lien valable jusqu'au {{ $expiration }}.@endsection
@section('titre'){{ $role === 'professeur' ? "Bienvenue dans l'équipe !" : 'Votre accès est prêt' }}@endsection
@section('contenu')
<p style="margin:0 0 16px;">Bonjour {{ $prenom }},<br>
@if ($role === 'professeur')
{{ $par ?? 'La direction de votre école' }} a créé votre compte sur <strong>{{ $ecole }}</strong>, l'espace où vous retrouvez vos classes et vos séances, et où vous encodez vos heures.
@else
{{ $par ?? 'La direction de votre école' }} vous a donné un accès <strong>{{ $role }}</strong> à {{ $ecole }} : classes, calendrier, professeurs et validation des heures.
@endif
Pour l'activer, choisissez votre mot de passe :</p>
@include('mail.acces._bouton', ['url' => $lien, 'libelle' => 'Choisir mon mot de passe'])
<div style="background:#e7f5f2;border-left:4px solid #0e8f79;padding:12px 16px;font-size:15px;margin:0 0 16px;">
<strong>Vos infos de connexion</strong><br>Identifiant : <strong>{{ $email }}</strong><br>Lien valable jusqu'au <strong>{{ $expiration }}</strong>, utilisable une seule fois.</div>
<p style="font-size:14px;color:#4b5a6b;margin:0 0 12px;"><strong>Le lien a expiré ?</strong> Ouvrez-le quand même : la page vous proposera d'en recevoir un nouveau. Ensuite, connectez-vous sur <a href="{{ $urlConnexion }}" style="color:#14568f;">{{ $urlConnexion }}</a>.</p>
<p style="font-size:14px;color:#4b5a6b;margin:0 0 12px;">Vous ne vous attendiez pas à cet email ? Ignorez-le : le compte reste inactif tant qu'aucun mot de passe n'est choisi. Nous ne vous demanderons jamais votre mot de passe par email.</p>
@include('mail.acces._lien-secours')
@endsection
@section('pied')Email automatique envoyé à {{ $email }} car un compte a été créé à votre nom.@endsection
