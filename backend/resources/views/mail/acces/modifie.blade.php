@extends('mail.acces.layout')
@section('preheader'){{ $activation ? 'Connectez-vous avec '.$email.' et votre nouveau mot de passe.' : 'Modification effectuée le '.$date.'.' }}@endsection
@section('titre'){{ $activation ? "C'est prêt !" : 'Mot de passe modifié' }}@endsection
@section('contenu')
<p style="margin:0 0 16px;">Bonjour {{ $prenom }},<br>Le mot de passe de votre compte <strong>{{ $email }}</strong> a été enregistré le <strong>{{ $date }}</strong>.</p>
@include('mail.acces._bouton', ['url' => $urlConnexion, 'libelle' => 'Me connecter'])
<p style="font-size:14px;color:#4b5a6b;margin:0 0 12px;">Par sécurité, vous avez été déconnecté(e) de vos autres appareils.</p>
<p style="font-size:14px;color:#4b5a6b;margin:0;"><strong>Ce n'était pas vous ?</strong> Choisissez tout de suite un nouveau mot de passe via <a href="{{ $urlOubli }}" style="color:#14568f;">Mot de passe oublié</a>, puis prévenez @if ($contact)<a href="mailto:{{ $contact }}" style="color:#14568f;">{{ $contact }}</a>@else<span>la direction de votre école</span>@endif.</p>
@endsection
@section('pied')Email automatique de sécurité envoyé à {{ $email }}.@endsection
