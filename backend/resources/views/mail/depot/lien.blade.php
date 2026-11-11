@extends('mail.acces.layout')
@section('preheader')Les fichiers sont téléchargeables jusqu'au {{ $expiration }}.@endsection
@section('titre', 'Les créations de votre enfant')
@section('contenu')
<p style="margin:0 0 16px;">Bonjour,<br>
{{ $stage ? 'Suite au stage « '.$stage.' », vous' : 'Vous' }} pouvez télécharger {{ $nbFichiers > 1 ? 'les '.$nbFichiers.' fichiers réalisés' : 'le fichier réalisé' }} par votre enfant.</p>
@include('mail.acces._bouton', ['url' => $lien, 'libelle' => 'Voir et télécharger les fichiers'])
<div style="background:#e7f5f2;border-left:4px solid #0e8f79;padding:12px 16px;font-size:15px;margin:0 0 16px;">
Lien valable jusqu'au <strong>{{ $expiration }}</strong>. Pensez à enregistrer les fichiers avant cette date.
</div>
<p style="font-size:14px;color:#4b5a6b;margin:0 0 12px;">Ce lien est personnel : toute personne qui le possède peut télécharger ces fichiers. Ne le partagez pas.</p>
@include('mail.acces._lien-secours')
@endsection
@section('pied', 'Email automatique envoyé à la demande de l\'école.')
