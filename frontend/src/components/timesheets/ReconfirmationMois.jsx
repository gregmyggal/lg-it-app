import { useState } from 'react';
import AdminButton from '../AdminButton';
import AdminModal from '../AdminModal';
import { AdminFormField, AdminTextarea } from '../AdminFormField';
import Banner from '../ui/Banner';
import { Table, Th, Td, Tr } from '../ui/Table';
import { contesterMois, signerMois, telechargerPdf, useMaConfirmation } from '../../hooks/useTimesheets';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage } from '../../api/errors';
import { formatDateCourte } from '../../utils/dates';
import { enregistrerBlob } from '../../utils/telechargement';
import { formatEuros, formatHeures } from '../../utils/format';

const CHAMPS = { nombre_heures: 'Heures', date_prestation: 'Date', type_activite: 'Type' };

function formatChamp(champ, v) {
  if (champ === 'nombre_heures') return formatHeures(v);
  if (champ === 'date_prestation') return formatDateCourte(v);
  return String(v);
}

function descriptionAjustement(a) {
  if (a.action === 'lissage') {
    return `${formatHeures(a.apres.heures_deplacees)} déplacées vers le ${formatDateCourte(a.apres.date_cible)} (${formatEuros(a.apres.montant_deplace)})`;
  }
  return Object.keys(a.apres).map((c) => `${CHAMPS[c] || c} : ${formatChamp(c, a.avant[c])} → ${formatChamp(c, a.apres[c])}`).join(' ; ');
}

/**
 * Reconfirmation du mois par le professeur (TS-01 T4) : ce que la direction a ajusté (avant → après, motif), puis
 * « Accepter et signer » ou « Contester » (motif obligatoire). Le serveur reste l'autorité sur ce qui est possible.
 */
export default function ReconfirmationMois({ professeurId, annee, mois, onChange }) {
  const toast = useToast();
  const conf = useMaConfirmation(annee, mois);
  const [contestation, setContestation] = useState(false);
  const [motif, setMotif] = useState('');
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);
  const d = conf.data;
  if (!d) return null;

  const attente = d.statut_mois === 'attente_prof';
  const termine = ['pret_pdf', 'genere'].includes(d.statut_mois);
  if (!attente && !d.contestation && !termine && d.ajustements.length === 0) return null;

  async function agir(fn, succes) {
    setEnvoi(true);
    setErreur(null);
    try {
      await fn();
      toast.success(succes);
      setContestation(false);
      setMotif('');
      conf.reload();
      onChange?.();
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <section aria-label="Confirmation du mois" style={{ marginBottom: 16 }}>
      {d.contestation && (
        <Banner tone="warning">
          <strong>Contestation envoyée à la direction.</strong> Votre motif : « {d.contestation.motif} ». Vos heures sont en cours de révision ; vous serez prévenu(e) pour les confirmer.
        </Banner>
      )}
      {d.derniere_reponse && !d.contestation && (
        <Banner tone="info">Réponse de la direction : « {d.derniere_reponse.motif} ».</Banner>
      )}
      {termine && (
        <Banner tone="success">
          <strong>Mois confirmé.</strong> {d.pdf ? 'Votre fiche de défraiement est disponible.' : 'Le PDF sera généré par la direction.'}
          {d.pdf && (
            <AdminButton
              size="sm"
              style={{ marginLeft: 12 }}
              onClick={() => telechargerPdf(d.pdf.id).then((blob) => enregistrerBlob(blob, `${annee}${String(mois).padStart(2, '0')} Fiche de défraiement.pdf`)).catch((err) => setErreur(getErrorMessage(err)))}
            >
              Télécharger ma fiche PDF
            </AdminButton>
          )}
        </Banner>
      )}

      {(attente || d.ajustements.length > 0) && !d.contestation && (
        <div style={{ border: '1px solid var(--c-border)', borderRadius: 8, background: 'var(--c-card)', padding: 16 }}>
          {attente && (
            <>
              <h3 style={{ margin: '0 0 4px' }}>{d.ajustements.length > 0 ? 'Votre mois a été ajusté par la direction' : 'Vos heures sont validées'}</h3>
              <p style={{ margin: '0 0 12px' }}>Vérifiez-les puis confirmez-les en signant, ou contestez-les avec un motif.</p>
            </>
          )}
          {d.ajustements.length > 0 && (
            <Table caption="Ajustements de la direction" minWidth="560px">
              <thead><tr><Th>Saisie du</Th><Th>Ajustement</Th><Th>Motif</Th></tr></thead>
              <tbody>
                {d.ajustements.map((a) => (
                  <Tr key={a.id}>
                    <Td>{a.date_prestation ? formatDateCourte(a.date_prestation) : '—'}</Td>
                    <Td>{descriptionAjustement(a)}</Td>
                    <Td>{a.motif}</Td>
                  </Tr>
                ))}
              </tbody>
            </Table>
          )}
          {erreur && <Banner tone="error" role="alert">{erreur}</Banner>}
          {attente && (
            <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginTop: 12 }}>
              <AdminButton
                loading={envoi}
                disabled={!d.peut_signer}
                onClick={() => agir(() => signerMois(professeurId, annee, mois), 'Mois confirmé et signé. Merci !')}
              >
                Accepter et signer
              </AdminButton>
              <AdminButton variant="secondary" disabled={!d.peut_contester || envoi} onClick={() => setContestation(true)}>Contester…</AdminButton>
            </div>
          )}
          {attente && !d.peut_signer && d.erreurs_signature.length > 0 && (
            <p style={{ fontSize: 13, color: 'var(--tone-warning-fg)' }}>{d.erreurs_signature.join(' · ')}</p>
          )}
        </div>
      )}

      {contestation && (
        <AdminModal
          isOpen
          title="Contester mes heures"
          size="sm"
          onClose={() => setContestation(false)}
          footer={
            <>
              <AdminButton variant="secondary" onClick={() => setContestation(false)}>Annuler</AdminButton>
              <AdminButton
                loading={envoi}
                disabled={motif.trim().length < 3}
                onClick={() => agir(() => contesterMois({ annee, mois, motif: motif.trim() }), 'Contestation envoyée à la direction.')}
              >
                Envoyer la contestation
              </AdminButton>
            </>
          }
        >
          <AdminFormField label="Motif (obligatoire)" htmlFor="contestation-motif">
            <AdminTextarea id="contestation-motif" rows={3} value={motif} onChange={(e) => setMotif(e.target.value)} />
          </AdminFormField>
          <p style={{ fontSize: 13, margin: 0 }}>Le mois repasse en revue chez la direction et la génération du PDF est suspendue.</p>
        </AdminModal>
      )}
    </section>
  );
}
