import { useEffect, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminTextarea } from '../AdminFormField';
import Banner from '../ui/Banner';
import { LoadingBlock } from '../ui/DataStates';
import { apercuLissage, appliquerLissage } from '../../hooks/useTimesheets';
import { getErrorData, getErrorMessage } from '../../api/errors';
import { formatDateCourte } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';

/**
 * Lissage d'un mois (TS-01 T3) : répartir des heures sur d'autres jours du MÊME mois sans changer le total.
 * - sans `saisie` : mode automatique (proposition pour tous les jours au-delà du plafond) ;
 * - avec `saisie` : mode manuel (date cible + montant à déplacer depuis cette saisie).
 * Aperçu avant/après calculé par le serveur ; motif obligatoire ; application atomique.
 */
export default function LisserMoisModal({ professeurId, annee, mois, saisie, onClose, onDone }) {
  const manuel = Boolean(saisie);
  const [cible, setCible] = useState('');
  const [montant, setMontant] = useState('');
  const [motif, setMotif] = useState('');
  const [apercu, setApercu] = useState(null);
  const [chargement, setChargement] = useState(!manuel);
  const [erreur, setErreur] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  async function calculer(payload) {
    setChargement(true);
    setErreur(null);
    try {
      setApercu(await apercuLissage(professeurId, { annee, mois, ...payload }));
    } catch (err) {
      setApercu(null);
      setErreur(getErrorMessage(err, 'Aperçu impossible'));
    } finally {
      setChargement(false);
    }
  }

  useEffect(() => {
    if (!manuel) calculer({ mode: 'auto' });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const deplacementManuel = () => [{ timesheet_id: saisie.id, date_to: cible, montant: Number(String(montant).replace(',', '.')) }];

  const apercuManuel = () => calculer({ mode: 'manuel', deplacements: deplacementManuel() });

  async function appliquer() {
    setEnvoi(true);
    setErreur(null);
    try {
      const r = await appliquerLissage(professeurId, { annee, mois, deplacements: apercu.deplacements.map(({ timesheet_id, date_to, montant: m }) => ({ timesheet_id, date_to, montant: m })), motif: motif.trim() });
      onDone(`Lissage appliqué (${r.deplacements} déplacement(s)). Le total du mois est inchangé.`);
    } catch (err) {
      setErreur(getErrorData(err)?.errors?.deplacements?.[0] || getErrorMessage(err, 'Lissage impossible'));
    } finally {
      setEnvoi(false);
    }
  }

  const peutAppliquer = apercu && apercu.erreurs.length === 0 && apercu.deplacements.length > 0 && motif.trim().length >= 3 && !envoi;

  return (
    <AdminModal
      isOpen
      size="lg"
      title={manuel ? `Lisser la saisie du ${formatDateCourte(saisie.date_prestation)}` : 'Lisser le mois'}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton onClick={appliquer} disabled={!peutAppliquer}>{envoi ? 'Application…' : 'Appliquer le lissage'}</AdminButton>
        </>
      }
    >
      {erreur && <Banner tone="error" role="alert">{erreur}</Banner>}

      {manuel && (
        <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'flex-end' }}>
          <AdminFormField label="Déplacer vers le" htmlFor="lisser-date">
            <AdminInput id="lisser-date" type="date" value={cible} onChange={(e) => setCible(e.target.value)} />
          </AdminFormField>
          <AdminFormField label="Montant (€)" htmlFor="lisser-montant" description={`Saisie d’origine : ${formatHeures(saisie.nombre_heures)}, ${formatEuros(saisie.montant_brut)}`}>
            <AdminInput id="lisser-montant" inputMode="decimal" value={montant} onChange={(e) => setMontant(e.target.value)} />
          </AdminFormField>
          <AdminFormField label=" ">
            <AdminButton variant="secondary" onClick={apercuManuel} disabled={!cible || !montant || chargement}>Voir l’aperçu</AdminButton>
          </AdminFormField>
        </div>
      )}

      {chargement && <LoadingBlock message="Calcul de l’aperçu…" lignes={2} />}

      {apercu && (
        <div aria-live="polite">
          {apercu.deplacements.length === 0 && apercu.erreurs.length === 0 && (
            <Banner tone="info">Aucun jour ne dépasse le plafond de {formatEuros(apercu.plafond_journalier_eur)} : aucun lissage nécessaire.</Banner>
          )}
          {apercu.non_resolus?.length > 0 && (
            <Banner tone="warning">
              Impossible de tout lisser automatiquement : {apercu.non_resolus.map((n) => `${formatDateCourte(n.date)} (+${formatEuros(n.depassement)})`).join(', ')}. Adaptez ces jours manuellement.
            </Banner>
          )}
          {apercu.erreurs.map((e) => <Banner key={e} tone="error">{e}</Banner>)}
          {apercu.jours.length > 0 && (
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13, margin: '8px 0' }}>
              <thead><tr><th style={{ textAlign: 'left' }}>Jour</th><th style={{ textAlign: 'right' }}>Avant</th><th style={{ textAlign: 'right' }}>Après</th></tr></thead>
              <tbody>
                {apercu.jours.map((j) => (
                  <tr key={j.date}>
                    <td>{formatDateCourte(j.date)}</td>
                    <td style={{ textAlign: 'right' }}>{formatEuros(j.avant)}</td>
                    <td style={{ textAlign: 'right', fontWeight: 600 }}>{formatEuros(j.apres)}</td>
                  </tr>
                ))}
                <tr style={{ borderTop: '1px solid var(--c-border)' }}>
                  <th style={{ textAlign: 'left' }}>Total du mois</th>
                  <th style={{ textAlign: 'right' }}>{formatEuros(apercu.total_avant)}</th>
                  <th style={{ textAlign: 'right' }}>{formatEuros(apercu.total_apres)} {apercu.total_avant === apercu.total_apres ? '(inchangé ✓)' : ''}</th>
                </tr>
              </tbody>
            </table>
          )}
        </div>
      )}

      <AdminFormField label="Motif (obligatoire)" htmlFor="lisser-motif">
        <AdminTextarea id="lisser-motif" rows={2} value={motif} onChange={(e) => setMotif(e.target.value)} placeholder="Ex. : respect du plafond journalier" />
      </AdminFormField>
    </AdminModal>
  );
}
