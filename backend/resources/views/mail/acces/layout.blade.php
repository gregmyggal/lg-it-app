{{-- Gabarit commun des emails d'accès : tables + styles inline, aucune image (compatibilité clients mail). --}}
<!DOCTYPE html>
<html lang="fr-BE"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light only">
<title>{{ $objet }}</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#16212e;">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">@yield('preheader')&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
<tr><td style="background:#0b3550;padding:18px 28px;border-radius:8px 8px 0 0;color:#ffffff;font-weight:bold;font-size:18px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#d9531e;margin-right:8px;"></span>{{ $ecole }}</td></tr>
<tr><td style="background:#ffffff;padding:28px;font-size:16px;line-height:1.6;border-radius:0 0 8px 8px;">
<h1 style="font-size:22px;line-height:1.3;margin:0 0 12px;color:#16212e;">@yield('titre')</h1>
@yield('contenu')
</td></tr>
<tr><td style="padding:14px 28px;font-size:12px;line-height:1.5;color:#4b5a6b;">{{ $ecole }} · @yield('pied')@if ($contact) Une question ? Écrivez à <a href="mailto:{{ $contact }}" style="color:#14568f;">{{ $contact }}</a>.@endif</td></tr>
</table></td></tr></table></body></html>
