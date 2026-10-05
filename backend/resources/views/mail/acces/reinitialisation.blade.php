@extends('mail.acces.layout')
@section('preheader')@if ($parDirection)Lien valable jusqu'au {{ $expiration }}. Votre mot de passe actuel reste valable d'ici là.@else Vous avez demandé un nouveau mot de passe. Lien valable jusqu'à {{ $expiration }}.@endif @endsection
@section('titre'){{ $parDirection ? 'Choisissez un nouveau mot de passe' : 'Mot de passe oublié ?' }}@endsection
@section('contenu')
<p style="margin:0 0 16px;">Bonjour {{ $prenom }},<br>
@if ($parDirection)
{{ $par ? $par.' (direction)' : 'La direction de votre école' }} vous a envoyé un lien pour choisir un nouveau mot de passe sur {{ $ecole }}.
@else
Vous avez demandé à réinitialiser le mot de passe de votre compte {{ $ecole }}.
@endif
</p>
@include('mail.acces._bouton', ['url' => $lien, 'libelle' => 'Choisir un nouveau mot de passe'])
<div style="background:#e7f5f2;border-left:4px solid #0e8f79;padding:12px 16px;font-size:15px;margin:0 0 16px;">
Identifiant : <strong>{{ $email }}</strong><br>
@if ($parDirection)Lien valable jusqu'au <strong>{{ $expiration }}</strong>, une seule fois.@else Valable <strong>{{ $validite }}</strong>, jusqu'à <strong>{{ $expiration }}</strong>, une seule fois.@endif
</div>
@if ($parDirection)
<p style="font-size:14px;color:#4b5a6b;margin:0 0 12px;">Tant que vous n'avez pas choisi de nouveau mot de passe, l'ancien continue de fonctionner.</p>
@endif
<p style="font-size:14px;color:#4b5a6b;margin:0 0 12px;"><strong>Le lien a expiré ?</strong> Demandez-en un nouveau sur la page <a href="{{ $urlOubli }}" style="color:#14568f;">Mot de passe oublié</a>.</p>
<p style="font-size:14px;color:#4b5a6b;margin:0 0 12px;">Vous n'avez rien demandé ? Ignorez cet email : votre mot de passe actuel ne change pas.</p>
@include('mail.acces._lien-secours')
@endsection
@section('pied')Email automatique envoyé à {{ $email }} suite à une demande sur la plateforme.@endsection
