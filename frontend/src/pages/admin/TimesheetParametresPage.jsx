import { useEffect, useState } from 'react';
import client from '../../api/client';
import { ADMIN_SPACING } from '../../styles/AdminDesignSystem';
import { AdminPageHeader, AdminPageContent, AdminCard, AdminCardHeader, AdminCardBody } from '../../components/AdminPageLayout';
import { AdminFormField, AdminInput, AdminSelect } from '../../components/AdminFormField';
import AdminButton from '../../components/AdminButton';
import Banner from '../../components/ui/Banner';
import { LoadingBlock } from '../../components/ui/DataStates';
import { useToast } from '../../hooks/useToast';
import { formatDateHeure } from '../../utils/dates';
import { formatDuree } from '../../utils/format';
import ImpactDefrayage from '../../components/ImpactDefrayage';
import SignatureParametresCard from '../../components/signature/SignatureParametresCard';
import { getErrorMessage, getFieldErrors } from '../../api/errors';

const ANNEE_COURANTE = new Date().getFullYear();
const ANNEES = [ANNEE_COURANTE + 1, ANNEE_COURANTE, ANNEE_COURANTE - 1, ANNEE_COURANTE - 2];

/** Plafonds de défraiement par année civile (directeur et admin). Le serveur reste la source de vérité. */
export default function TimesheetParametresPage() {
  const toast = useToast();
  const [annee, setAnnee] = useState(ANNEE_COURANTE);
  const [donnees, setDonnees] = useState(null);
  const [historique, setHistorique] = useState([]);
  const [journalier, setJournalier] = useState('');
  const [annuel, setAnnuel] = useState('');
  const [deplacement, setDeplacement] = useState('');
  const [seance, setSeance] = useState('');
  const [defrayables, setDefrayables] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [erreurGlobale, setErreurGlobale] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  async function charger(a) {
    setDonnees(null);
    try {
      const res = await client.get(`/timesheet-parametres/${a}`);
      setDonnees(res.data.data);
      setHistorique(res.data.historique);
      setJournalier(String(res.data.data.plafond_journalier_eur));
      setAnnuel(String(res.data.data.plafond_annuel_eur));
      setDeplacement(String(res.data.data.frais_deplacement_eur));
      setSeance(String(res.data.data.duree_seance_defaut));
      setDefrayables(String(res.data.data.heures_defrayables));
      setErreurGlobale(null);
    } catch (err) {
      setErreurGlobale(getErrorMessage(err, 'Impossible de charger les paramètres'));
    }
  }

  useEffect(() => {
    charger(annee);
  }, [annee]);

  async function enregistrer(e) {
    e.preventDefault();
    setEnvoi(true);
    setErreurs({});
    setErreurGlobale(null);
    try {
      await client.put(`/timesheet-parametres/${annee}`, {
        plafond_journalier_eur: journalier.replace(',', '.'),
        plafond_annuel_eur: annuel.replace(',', '.'),
        frais_deplacement_eur: deplacement.replace(',', '.'),
        duree_seance_defaut: seance.replace(',', '.'),
        heures_defrayables: defrayables.replace(',', '.'),
      });
      toast.success(`Paramètres ${annee} enregistrés. Ils s’appliquent aux séances non encore encodées.`);
      charger(annee);
    } catch (err) {
      setErreurs(getFieldErrors(err));
      setErreurGlobale(getErrorMessage(err, 'Enregistrement impossible'));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <>
      <AdminPageHeader
        icon="⚙️"
        title="Paramètres des timesheets"
        description="Heures défrayables, plafonds de défraiement et signature électronique des fiches"
      />
      <AdminPageContent>
        {erreurGlobale && <Banner tone="error" role="alert">{erreurGlobale}</Banner>}
        <AdminCard>
          <AdminCardHeader title="Paramètres par année civile" />
          <AdminCardBody>
            <AdminFormField label="Année" htmlFor="param-annee">
              <AdminSelect
                id="param-annee"
                value={annee}
                options={ANNEES.map((a) => ({ value: a, label: String(a) }))}
                onChange={(e) => setAnnee(Number(e.target.value))}
              />
            </AdminFormField>
            {!donnees ? (
              <LoadingBlock />
            ) : (
              <form onSubmit={enregistrer} noValidate>
                {donnees.herite && (
                  <Banner tone="info">
                    Aucun paramétrage pour {annee} : les valeurs de l’année précédente s’appliquent. Enregistrez pour les fixer.
                  </Banner>
                )}
                <h3 style={{ margin: '4px 0 8px' }}>Heures de séance et heures défrayables</h3>
                <AdminFormField
                  label="Durée d’une séance (heures)"
                  htmlFor="param-seance"
                  error={erreurs.duree_seance_defaut}
                  description={`= ${formatDuree(seance)}. Préremplit l’heure de fin à la création d’une classe (modifiable classe par classe).`}
                >
                  <AdminInput id="param-seance" inputMode="decimal" value={seance} onChange={(e) => setSeance(e.target.value)} />
                </AdminFormField>
                <AdminFormField
                  label="Heures défrayables par séance (heures)"
                  htmlFor="param-defrayables"
                  error={erreurs.heures_defrayables}
                  description={`= ${formatDuree(defrayables)}. Durée payée au professeur : cours + préparation. Entre 0,5 et 8 h, par pas de 15 min.`}
                >
                  <AdminInput id="param-defrayables" inputMode="decimal" value={defrayables} onChange={(e) => setDefrayables(e.target.value)} />
                </AdminFormField>
                {Number(defrayables.replace(',', '.')) > Number(seance.replace(',', '.')) ? (
                  <Banner tone="info">
                    {formatDuree(seance)} de séance + {formatDuree(Number(defrayables.replace(',', '.')) - Number(seance.replace(',', '.')))} de préparation = <strong>{formatDuree(defrayables)} défrayées</strong>.
                  </Banner>
                ) : (
                  <Banner tone="warning">Les heures défrayables sont inférieures à la durée d’une séance : le professeur serait défrayé moins que la durée du cours.</Banner>
                )}
                <ImpactDefrayage annee={annee} portee="global" heures={defrayables} />
                <p style={{ fontSize: 12, color: 'var(--c-text-2)' }}>
                  S’applique aux séances non encore encodées. Les heures déjà encodées (brouillons compris) ne changent pas.
                </p>
                <hr style={{ border: 0, borderTop: '1px solid var(--c-border)', margin: '16px 0' }} />
                <h3 style={{ margin: '0 0 8px' }}>Plafonds et déplacements</h3>
                <AdminFormField label="Plafond journalier (€/jour)" htmlFor="param-journalier" error={erreurs.plafond_journalier_eur}>
                  <AdminInput id="param-journalier" inputMode="decimal" value={journalier} onChange={(e) => setJournalier(e.target.value)} />
                </AdminFormField>
                <AdminFormField label="Plafond annuel (€/an)" htmlFor="param-annuel" error={erreurs.plafond_annuel_eur}>
                  <AdminInput id="param-annuel" inputMode="decimal" value={annuel} onChange={(e) => setAnnuel(e.target.value)} />
                </AdminFormField>
                <AdminFormField label="Frais de déplacement (€ par déplacement)" htmlFor="param-deplacement" error={erreurs.frais_deplacement_eur}>
                  <AdminInput id="param-deplacement" inputMode="decimal" value={deplacement} onChange={(e) => setDeplacement(e.target.value)} />
                </AdminFormField>
                <p style={{ fontSize: 12, color: 'var(--c-text-2)' }}>
                  Les plafonds servent aux alertes et au lissage et ne sont pas imprimés sur le PDF. Le forfait multiplie le nombre de déplacements encodés. Chaque modification est tracée.
                </p>
                <AdminButton type="submit" disabled={envoi}>{envoi ? 'Enregistrement…' : 'Enregistrer'}</AdminButton>
              </form>
            )}
          </AdminCardBody>
        </AdminCard>

        <div style={{ marginTop: ADMIN_SPACING.xl }}>
          <SignatureParametresCard />
        </div>

        {historique.length > 0 && (
          <div style={{ marginTop: ADMIN_SPACING.xl }}>
          <AdminCard>
            <AdminCardHeader title={`Historique ${annee}`} />
            <AdminCardBody>
              <ul style={{ margin: 0, paddingLeft: 18, fontSize: 13 }}>
                {historique.map((h) => (
                  <li key={h.id}>
                    {formatDateHeure(h.created_at)} — {h.auteur || 'Système'} : journalier {h.plafond_journalier_avant} → {h.plafond_journalier_apres} €, annuel {h.plafond_annuel_avant} → {h.plafond_annuel_apres} €{h.heures_defrayables_apres != null && ` · défrayé ${formatDuree(h.heures_defrayables_avant)} → ${formatDuree(h.heures_defrayables_apres)}, séance ${formatDuree(h.duree_seance_avant)} → ${formatDuree(h.duree_seance_apres)}`}
                  </li>
                ))}
              </ul>
            </AdminCardBody>
          </AdminCard>
          </div>
        )}
      </AdminPageContent>
    </>
  );
}
