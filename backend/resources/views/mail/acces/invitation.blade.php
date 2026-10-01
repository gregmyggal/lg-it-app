<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#16212e;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" style="max-width:600px;background:#ffffff;border-radius:8px;padding:32px;">
<tr><td style="font-size:15px;line-height:1.6;">
<p>Bonjour {{ $nom }},</p>
<p>Un compte <strong>{{ $role }}</strong> vient d'être créé pour vous sur la plateforme Logiscool Pays Vert. Pour l'activer, choisissez votre mot de passe :</p>
<p style="text-align:center;margin:28px 0;"><a href="{{ $lien }}" style="background:#d9531e;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:bold;">Définir mon mot de passe</a></p>
<p>Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br><span style="word-break:break-all;">{{ $lien }}</span></p>
<p>Ce lien est valable {{ $validite }} et ne peut être utilisé qu'une seule fois.</p>
<p style="color:#4b5a6b;font-size:13px;">Si vous n'attendiez pas cet email, ignorez-le : aucun compte n'est actif sans votre action.</p>
</td></tr></table></td></tr></table></body></html>
