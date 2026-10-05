# TS-01 T5 — PDF « Fiche de défraiement – Volontariat »

**Statut :** ☑ Livré · **Modèle :** fiche Logiscool « Fiche de défraiement – Volontariat » (juin 2026) · **Maquette :** écran D

## Format du PDF (conforme au modèle)
Logo Logiscool · titre « FICHE DE DÉFRAIEMENT - VOLONTARIAT » · « MOIS DE : OCTOBRE 2026 » · « Nom et Prénom du volontaire : NOM Prénom » · « Compte bancaire n° : IBAN groupé par 4 » · tableau DATE / OBJET / DÉFRAIEMENT / NOMBRE / TOTAL sur **15 lignes par page** (lignes vides à 0,00 €, une page supplémentaire au-delà), ligne TOTAL · bloc « Date et signature du volontaire » (SIG-01 : image de la signature, mention horodatée, identifiant, QR de vérification optionnel ; mois signés avant SIG-01 : « date — signé électroniquement par le volontaire ») · pied de page ASBL (`config/logiscool.php`).
Le lissage et les plafonds **ne sont pas imprimés**. Les saisies identiques d'un même jour (date, objet, montant unitaire) sont regroupées sur une ligne. Nom de fichier : `AAAAMM Fiche de défraiement_Prénom Nom.pdf`.

## Types de lignes (alignés sur le modèle)
Animation, Cours, Préparation (heures × tarif horaire du professeur) et **Frais de déplacement** (nombre × forfait annuel, paramètre `frais_deplacement_eur`, 7,50 € par défaut, modifiable dans « Paramètres timesheets »). Un frais de déplacement ne se lisse pas.

## Règles
- Génération (directeur ou admin, tous les professeurs) seulement si le mois est « Prêt PDF » (toutes saisies confirmées et signées, aucune contestation), compte bancaire renseigné, tarifs présents ; sinon 422 avec les motifs.
- Génération : nouvelle version enregistrée (`timesheet_pdfs`), saisies → `genere` (verrouillées).
- Lot : zip de fiches, tout ou rien (422 + liste des professeurs bloquants).
- Déverrouillage (admin seul, motif obligatoire, tracé) : saisies `genere` → `confirme`, signatures conservées ; la génération suivante crée la version N+1.
- Aperçu (staff) sans effet. Téléchargement : staff, ou le professeur concerné pour sa propre fiche.

## API
`POST /api/professeurs/{id}/timesheet-pdfs` · `GET …/timesheet-pdfs` · `GET …/timesheet-pdfs/apercu` · `POST /api/timesheets-mois/pdf-lot` · `POST …/timesheets-mois/deverrouiller` · `GET /api/timesheet-pdfs/{id}/telecharger`. Les anciens `generate-pdf` et `download-pdf` sont supprimés.

## UI
Détail professeur : « Aperçu PDF », « Générer le PDF » (désactivé avec les motifs), « Télécharger (vN) », « Déverrouiller… » (admin). Synthèse : « Générer les PDF (zip) ». Page du professeur : « Télécharger ma fiche PDF » une fois générée.

## Tests
`backend/tests/Feature/TimesheetPdfTest.php` (7 cas : types et montants, génération, blocages, droits, lot atomique, déverrouillage/versions, aperçu et page 2) + forfait dans `TimesheetParametresTest`.
