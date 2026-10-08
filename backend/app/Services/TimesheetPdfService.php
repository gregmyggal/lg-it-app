<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Employeur;
use App\Models\AccesDonneeSensible;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\TimesheetPdf;
use App\Models\User;
use App\Support\FrontendUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use ZipArchive;

/**
 * TS-01 T5 : fiche de défraiement « Fiche de défraiement – Volontariat » (modèle Logiscool) : logo, titre, mois, nom
 * et compte du volontaire, tableau DATE / OBJET / DÉFRAIEMENT / NOMBRE / TOTAL sur 15 lignes par page (page suivante
 * au-delà), total, date et signature, pied de page de l'employeur du mois (EMP-01 : ASBL ou L-IT Solutions). Le lissage n'apparaît pas : seules les saisies finales comptent.
 */
class TimesheetPdfService
{
    public const LIGNES_PAR_PAGE = 15;

    public function __construct(
        private readonly TimesheetConfirmationService $confirmation,
        private readonly TarifResolver $tarifs,
        private readonly FicheDefraiementLignes $fiche,
        private readonly SignatureNumeriqueService $signatures,
        private readonly EmployeurMoisService $employeurs,
    ) {}

    /** Motifs qui empêchent la génération (liste vide = prêt). */
    public function bloquants(Professeur $prof, int $annee, int $mois): array
    {
        $statut = $this->confirmation->statutMois($prof, $annee, $mois);
        $bloquants = [];
        $messages = [
            TimesheetSyntheseMoisService::STATUT_BROUILLON => 'Aucune saisie soumise pour ce mois',
            TimesheetSyntheseMoisService::STATUT_A_VALIDER => 'Saisies à valider par la direction',
            TimesheetSyntheseMoisService::STATUT_ATTENTE_PROF => 'En attente de la signature du professeur',
            TimesheetSyntheseMoisService::STATUT_CONTESTE => 'Contestation en cours',
            TimesheetSyntheseMoisService::STATUT_GENERE => 'PDF déjà généré (déverrouillage admin requis pour en refaire un)',
        ];
        if ($statut !== TimesheetSyntheseMoisService::STATUT_PRET_PDF) {
            $bloquants[] = $messages[$statut] ?? 'Mois non prêt';
        }
        if (blank($prof->compte_bancaire)) {
            $bloquants[] = 'Compte bancaire manquant';
        }
        $employeur = $this->employeurs->effectif($prof, $annee, $mois);
        if (! $employeur->coordonneesCompletes()) {
            $bloquants[] = 'Coordonnées de l\'employeur « '.$employeur->nom.' » à compléter (Admin > Entités employeurs)';
        }
        if ($statut === TimesheetSyntheseMoisService::STATUT_PRET_PDF && $this->signatures->aResigner($prof, $annee, $mois)) {
            $bloquants[] = 'Données modifiées depuis la signature : nouvelle signature du professeur requise';
        }
        if ($statut === TimesheetSyntheseMoisService::STATUT_PRET_PDF) {
            $tarifsProf = ProfesseurTarif::where('professeur_id', $prof->id)->get();
            if ($this->saisies($prof, $annee, $mois)->contains(fn (Timesheet $t) => $this->tarifs->unite($t, $tarifsProf) === null)) {
                $bloquants[] = 'Saisie sans tarif';
            }
        }

        return $bloquants;
    }

    /** Génère et enregistre la fiche (nouvelle version) ; les saisies du mois passent à « généré ». */
    public function generer(Professeur $prof, int $annee, int $mois, User $auteur): TimesheetPdf
    {
        return DB::transaction(function () use ($prof, $annee, $mois, $auteur) {
            $saisies = $this->saisies($prof, $annee, $mois, verrou: true);
            $bloquants = $this->bloquants($prof, $annee, $mois);
            if ($bloquants !== []) {
                throw RegleMetierException::invalide('Génération impossible : '.implode(' ; ', $bloquants).'.', ['pdf' => $bloquants]);
            }

            // RG-3/RG-8 : l'employeur du mois est figé sur la fiche (ligne « fige » + snapshot des coordonnées).
            $employeur = $this->employeurs->materialiser($prof, $annee, $mois, $auteur, 'Fixé automatiquement à la génération du PDF');
            $contenu = $this->contenu($prof, $annee, $mois, $saisies, $employeur);
            $version = (int) TimesheetPdf::where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois])->max('version') + 1;
            $chemin = sprintf('pdfs/%d/%04d-%02d-v%d.pdf', $prof->id, $annee, $mois, $version);
            Storage::disk('local')->put($chemin, $contenu['pdf']);

            $pdf = TimesheetPdf::create([
                'professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois, 'version' => $version,
                'chemin' => $chemin, 'total_eur' => $contenu['total'], 'generated_by' => $auteur->id, 'generated_at' => now(),
                'employeur_id' => $employeur->id, 'employeur_snapshot' => $employeur->snapshot(),
            ]);

            Timesheet::whereIn('id', $saisies->pluck('id'))->update([
                'statut_validation' => Timesheet::STATUT_GENERE,
                'pdf_generated_at' => now(),
                'pdf_generated_by' => $auteur->id,
            ]);

            return $pdf->load('professeur');
        });
    }

    /** Aperçu : mêmes données que la génération, sans rien enregistrer ni changer de statut (accepte un mois non prêt). */
    public function apercu(Professeur $prof, int $annee, int $mois): string
    {
        return $this->contenu($prof, $annee, $mois, $this->saisies($prof, $annee, $mois), $this->employeurs->effectif($prof, $annee, $mois))['pdf'];
    }

    /**
     * Génère un lot et renvoie le chemin d'un zip temporaire. Tout est vérifié d'abord : si un professeur bloque,
     * rien n'est généré et les bloquants sont listés.
     *
     * @param  int[]  $professeurIds
     * @return array{zip: string, nombre: int}
     */
    public function lot(array $professeurIds, int $annee, int $mois, User $auteur): array
    {
        $profs = Professeur::whereIn('id', $professeurIds)->get();
        $bloques = [];
        foreach ($profs as $p) {
            $b = $this->bloquants($p, $annee, $mois);
            if ($b !== []) {
                $bloques[] = ['professeur_id' => $p->id, 'professeur' => trim($p->prenom.' '.$p->nom), 'raisons' => $b];
            }
        }
        if ($bloques !== []) {
            throw RegleMetierException::invalide(count($bloques).' professeur(s) ne sont pas prêts. Aucun PDF n\'a été généré.', ['professeur_ids' => ['Professeurs non prêts.']], ['bloquants' => $bloques]);
        }

        $zipChemin = tempnam(sys_get_temp_dir(), 'pdfs');
        $zip = new ZipArchive;
        $zip->open($zipChemin, ZipArchive::OVERWRITE);
        DB::transaction(function () use ($profs, $annee, $mois, $auteur, $zip) {
            foreach ($profs as $p) {
                $pdf = $this->generer($p, $annee, $mois, $auteur);
                $zip->addFromString($pdf->nomFichier(), Storage::disk('local')->get($pdf->chemin));
            }
        });
        $zip->close();

        foreach ($profs as $p) {
            app(JournalAccesService::class)->enregistrer($auteur, $p, AccesDonneeSensible::EXPORT_ZIP);
        }

        return ['zip' => $zipChemin, 'nombre' => $profs->count()];
    }

    /** Admin : rouvre un mois généré (saisies → confirmé, signatures conservées) ; la prochaine génération sera une nouvelle version. */
    public function deverrouiller(Professeur $prof, int $annee, int $mois, string $motif, User $auteur): int
    {
        return DB::transaction(function () use ($prof, $annee, $mois, $motif, $auteur) {
            $saisies = $this->saisies($prof, $annee, $mois, verrou: true)->where('statut_validation', Timesheet::STATUT_GENERE);
            if ($saisies->isEmpty()) {
                throw RegleMetierException::invalide('Aucun PDF généré à déverrouiller pour ce mois.');
            }
            foreach ($saisies as $t) {
                $t->update(['statut_validation' => Timesheet::STATUT_CONFIRME]);
                TimesheetAudit::create([
                    'timesheet_id' => $t->id, 'professeur_id' => $prof->id, 'user_id' => $auteur->id,
                    'action' => TimesheetAudit::ACTION_DEVERROUILLAGE,
                    'avant' => ['statut' => Timesheet::STATUT_GENERE], 'apres' => ['statut' => Timesheet::STATUT_CONFIRME], 'motif' => $motif,
                ]);
            }

            return $saisies->count();
        });
    }

    /** @return Collection<int, Timesheet> */
    private function saisies(Professeur $prof, int $annee, int $mois, bool $verrou = false): Collection
    {
        return $this->fiche->saisies($prof, $annee, $mois, $verrou);
    }

    /** @return array{pdf: string, total: float} */
    private function contenu(Professeur $prof, int $annee, int $mois, Collection $saisies, Employeur $employeur): array
    {
        $lignes = $this->fiche->lignes($prof, $saisies);
        $signature = $this->blocSignature($prof, $annee, $mois, $saisies);
        $pages = $lignes->chunk(self::LIGNES_PAR_PAGE);
        if ($pages->isEmpty()) {
            $pages = collect([collect()]);
        }

        $html = $pages->map(fn ($page) => $this->htmlPage($prof, $annee, $mois, $page, $signature))->implode('<pagebreak />');

        $dir = storage_path('app/mpdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $mpdf = new Mpdf(['tempDir' => $dir, 'format' => 'A4', 'margin_top' => 12, 'margin_bottom' => 38, 'margin_left' => 20, 'margin_right' => 20, 'default_font' => 'dejavusans', 'default_font_size' => 9]);
        $mpdf->SetTitle('Fiche de défraiement – '.trim($prof->prenom.' '.$prof->nom));
        $mpdf->SetHTMLFooter($this->htmlPied($employeur));
        $mpdf->WriteHTML($html);

        return ['pdf' => $mpdf->Output('', 'S'), 'total' => round((float) $lignes->sum('total'), 2)];
    }

    private function htmlPage(Professeur $prof, int $annee, int $mois, Collection $page, string $signature): string
    {
        $mois = mb_strtoupper(Carbon::create($annee, $mois, 1)->locale('fr')->translatedFormat('F Y'));
        $nom = mb_strtoupper($prof->nom).' '.$prof->prenom;
        $compte = trim(chunk_split((string) $prof->compte_bancaire, 4, ' '));

        $corps = '';
        for ($i = 0; $i < self::LIGNES_PAR_PAGE; $i++) {
            $l = $page->get($i);
            $corps .= $l
                ? '<tr><td>'.Carbon::parse($l['date'])->format('d/m/Y').'</td><td>'.e($l['objet']).'</td><td class="r">'.$this->euro($l['unite']).'</td><td class="c">'.$this->nombre($l['nombre']).'</td><td class="r">'.$this->euro($l['total']).'</td></tr>'
                : '<tr><td>&nbsp;</td><td></td><td></td><td></td><td class="r">'.$this->euro(0).'</td></tr>';
        }
        $totalNombre = $page->sum('nombre');
        $total = $page->sum('total');
        $logo = resource_path('pdf/logo-logiscool.jpg');

        return <<<HTML
<div style="text-align:center"><img src="{$logo}" style="height:17mm"></div>
<h1 style="text-align:center;font-size:17pt;text-decoration:underline;margin:10mm 0 6mm">FICHE DE DÉFRAIEMENT - VOLONTARIAT</h1>
<h2 style="text-align:center;font-size:13pt;margin:0 0 9mm">MOIS DE : {$mois}</h2>
<p style="margin:0 0 4mm">Nom et Prénom du volontaire : {$nom}</p>
<p style="margin:0 0 5mm">Compte bancaire n° : {$compte}</p>
<table style="width:100%;border-collapse:collapse;font-size:8.5pt" border="1" cellpadding="1.2">
<thead><tr><th style="width:17%">DATE</th><th style="width:33%">OBJET</th><th style="width:17%">DÉFRAIEMENT</th><th style="width:15%">NOMBRE</th><th style="width:18%">TOTAL</th></tr></thead>
<tbody>{$corps}
<tr><th style="text-align:left">TOTAL</th><td></td><td></td><th>{$this->nombre($totalNombre)}</th><th class="r" style="text-align:right">{$this->euro($total)}</th></tr></tbody></table>
{$signature}
<style>td.r{text-align:right}td.c{text-align:center}</style>
HTML;
    }

    /**
     * SIG-01 : bloc « Date et signature » imprimé sur chaque page. Signature en vigueur et contenu inchangé : image
     * figée, mention, identifiant et (si émise avec vérification publique) QR de vérification. Mois signé avant SIG-01 :
     * mention textuelle d'origine. Contenu modifié depuis la signature : rien (la génération est d'ailleurs bloquée).
     */
    private function blocSignature(Professeur $prof, int $annee, int $mois, Collection $saisies): string
    {
        $sig = $this->signatures->derniere($prof, $annee, $mois);
        if (! $sig) {
            $date = $saisies->whereNotNull('signature_professeur')->max('signature_professeur');

            return '<p style="margin-top:9mm">Date et signature : '.($date ? Carbon::parse($date)->format('d/m/Y').' — signé électroniquement par le volontaire' : '').'</p>';
        }
        if ($this->signatures->estPerimee($sig)) {
            return '<p style="margin-top:9mm">Date et signature : </p>';
        }

        $image = Storage::disk('local')->path($sig->specimen_chemin);
        $le = $sig->signed_at->copy()->timezone('Europe/Brussels');
        $mention = 'Signé électroniquement par '.e($prof->prenom.' '.mb_strtoupper($prof->nom)).' le '.$le->format('d/m/Y').' à '.$le->format('H:i').' ('.$le->getTimezone()->getName().')'
            .'<br>ID '.e($sig->public_id).' · empreinte '.substr($sig->content_hash, 0, 4).'…'.substr($sig->content_hash, -4);
        $qr = '';
        if ($sig->verification_publique) {
            $url = FrontendUrl::lien('verif/'.$sig->public_id);
            $qr = '<barcode code="'.e($url).'" type="QR" size="0.55" error="M" disableborder="1" /><br><span style="font-size:5.5pt;color:#555">Vérifier : '.e(preg_replace('#^https?://#', '', $url)).'</span>';
        }

        return <<<HTML
<table style="width:100%;margin-top:6mm;border-collapse:collapse" cellpadding="0"><tr>
<td style="width:72%;vertical-align:bottom">Date et signature du volontaire :<br>
<div style="height:17mm;width:62mm;border-bottom:0.2mm solid #999"><img src="{$image}" style="max-height:16mm;max-width:60mm"></div>
<div style="font-size:6.5pt;color:#555;margin-top:1mm">{$mention}</div></td>
<td style="width:28%;text-align:right;vertical-align:bottom">{$qr}</td></tr></table>
HTML;
    }

    private function htmlPied(Employeur $employeur): string
    {
        $compte = $employeur->compteFormate();

        return '<div style="text-align:center"><b style="font-family:serif;font-size:12pt">'.e($employeur->nom).'</b><br><span style="font-family:serif;font-size:8pt">RPM : '.e($employeur->rpm)
            .($compte ? '<br>Banque : '.e($compte) : '').'<br>'.e($employeur->adresse).'</span></div>';
    }

    private function euro(float $v): string
    {
        return number_format($v, 2, ',', ' ').' €';
    }

    private function nombre(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
    }
}
