import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminTextarea } from '../AdminFormField';
import Banner from '../ui/Banner';
import StatutBadge from '../ui/StatutBadge';
import { Table, Th, Td, Tr } from '../ui/Table';
import { LoadingBlock, ErrorBlock } from '../ui/DataStates';
import { forcerSoumissionLot, useApercuSoumissionLot } from '../../hooks/useTimesheets';
import { getErrorMessage } from '../../api/errors';
import { formatDateCourte } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';

const RAISONS = { deja_soumis: 'mois déjà soumis', aucune_heure: 'aucune heure en brouillon' };

/**
 * TS-05 T2 : soumission forcée groupée depuis la synthèse. Liste, pour chaque professeur sélectionné, ce qui sera
 * soumis (le serveur décide de l'éligibilité) ; un message commun est obligatoire. Chaque professeur est traité
 * séparément : le résultat dit lesquels ont été soumis ou ignorés.
 *
 * @param {object} props
 * @param {number} props.annee
 * @param {number} props.mois
 * @param {number[]} props.professeurIds professeurs sélectionnés (50 au plus)
 * @param {string} props.titreMois « octobre 2026 »
 * @param {() => void} props.onClose fermeture sans résultat
 * @param {(resultats: object[]) => void} props.onTermine fermeture après envoi (la synthèse se recharge)
 */
export default function SoumettreGroupeModal({ annee, mois, professeurIds, titreMois, onClose, onTermine }) {
  const apercu = useApercuSoumissionLot(annee, mois, professeurIds);
  const [message, setMessage] = useState('');
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [resultat, setResultat] = useState(null);

  const eligibles = (apercu.data || []).filter((p) => p.possible);
  const ignores = (apercu.data || []).filter((p) => !p.possible);
  const total = eligibles.reduce((a, p) => ({ lignes: a.lignes + p.lignes, heures: a.heures + p.heures, eur: a.eur + p.total_eur }), { lignes: 0, heures: 0, eur: 0 });
  const valide = message.trim().length >= 3 && message.trim().length <= 500;

  async function envoyer() {
    setEnvoi(true);
    setErreur(null);
    try {
      setResultat(await forcerSoumissionLot({ annee, mois, professeur_ids: eligibles.map((p) => p.professeur_id), motif: message.trim() }));
    } catch (err) {
      setErreur(getErrorMessage(err, 'La soumission n\'a pas pu être envoyée. Rien n\'a été modifié ; réessayez.'));
    } finally {
      setEnvoi(false);
    }
  }

  if (resultat) {
    return (
      <AdminModal isOpen title="Résultat de la soumission" size="md" onClose={() => onTermine(resultat.resultats)} footer={<AdminButton onClick={() => onTermine(resultat.resultats)}>Fermer</AdminButton>}>
        <Banner tone={resultat.ignores > 0 ? 'warning' : 'success'}>
          <strong>
            {resultat.soumis} mois soumis{resultat.ignores > 0 ? `, ${resultat.ignores} ignoré${resultat.ignores > 1 ? 's' : ''}` : ''}.
          </strong>{' '}
          {resultat.soumis > 0 && 'Les professeurs concernés sont prévenus.'}
        </Banner>
        <Table caption="Résultat par professeur" minWidth="420px" cards>
          <thead><tr><Th>Professeur</Th><Th>Résultat</Th></tr></thead>
          <tbody>
            {resultat.resultats.map((r) => (
              <Tr key={r.professeur_id}>
                <Td label="Professeur">{r.nom}</Td>
                <Td label="Résultat">
                  {r.statut === 'soumis'
                    ? <><StatutBadge label="Soumis" tone="success" /> {r.lignes} ligne(s) · {formatEuros(r.total_eur)}</>
                    : <><StatutBadge label="Ignoré" tone="neutral" /> {r.raison}</>}
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      </AdminModal>
    );
  }

  return (
    <AdminModal
      isOpen
      title={`Soumettre le mois de ${eligibles.length > 0 ? `${eligibles.length} professeur${eligibles.length > 1 ? 's' : ''}` : 'la sélection'} à leur place`}
      size="md"
      onClose={envoi ? () => {} : onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose} disabled={envoi}>Annuler</AdminButton>
          {eligibles.length > 0 && (
            <AdminButton loading={envoi} disabled={!valide || envoi} onClick={envoyer}>
              Soumettre le mois de {eligibles.length} professeur{eligibles.length > 1 ? 's' : ''}
            </AdminButton>
          )}
        </>
      }
    >
      {erreur && <Banner tone="error" role="alert">{erreur}</Banner>}
      <p style={{ margin: '0 0 8px', fontSize: 13 }}>{titreMois}</p>
      {apercu.loading && !apercu.data && <LoadingBlock message="Vérification des professeurs sélectionnés…" lignes={3} />}
      {apercu.error && <ErrorBlock message={apercu.error} onRetry={apercu.reload} />}

      {apercu.data && eligibles.length === 0 && (
        <Banner tone="info">Aucun mois en brouillon dans la sélection : rien à soumettre.</Banner>
      )}

      {eligibles.length > 0 && (
        <>
          <Table caption="Professeurs dont le mois sera soumis" minWidth="560px" cards>
            <thead><tr><Th>Professeur</Th><Th>Lignes</Th><Th>Heures</Th><Th>Montant</Th><Th>Avertissement</Th></tr></thead>
            <tbody>
              {eligibles.map((p) => (
                <Tr key={p.professeur_id}>
                  <Td label="Professeur">{p.nom}</Td>
                  <Td label="Lignes">{p.lignes}</Td>
                  <Td label="Heures">{formatHeures(p.heures)}</Td>
                  <Td label="Montant">{formatEuros(p.total_eur)}</Td>
                  <Td label="Avertissement">
                    {p.seances_sans_heures.length > 0 && <StatutBadge label={`${p.seances_sans_heures.length} séance${p.seances_sans_heures.length > 1 ? 's' : ''} sans heures (${p.seances_sans_heures.map((d) => formatDateCourte(d)).join(', ')})`} tone="warning" />}
                    {p.depassements.length > 0 && <StatutBadge label={`plafond dépassé le ${p.depassements.map((d) => formatDateCourte(d)).join(', ')}`} tone="warning" />}
                  </Td>
                </Tr>
              ))}
            </tbody>
            <tfoot>
              <tr><Td label="Total"><strong>Total</strong></Td><Td label="Lignes"><strong>{total.lignes}</strong></Td><Td label="Heures"><strong>{formatHeures(total.heures)}</strong></Td><Td label="Montant"><strong>{formatEuros(total.eur)}</strong></Td><Td /></tr>
            </tfoot>
          </Table>

          {ignores.length > 0 && (
            <Banner tone="info" role="status">
              {ignores.length} professeur{ignores.length > 1 ? 's' : ''} sélectionné{ignores.length > 1 ? 's' : ''} {ignores.length > 1 ? 'sont ignorés' : 'est ignoré'} :{' '}
              {ignores.map((p) => `${p.nom} (${RAISONS[p.raison_impossible] || 'non éligible'})`).join(', ')}.
            </Banner>
          )}
          {eligibles.some((p) => !p.mois_termine) && (
            <Banner tone="warning">
              <strong>Le mois n'est pas terminé :</strong> les séances à venir ne seront pas incluses, et les professeurs ne pourront plus ajouter d'heures pour ce mois.
            </Banner>
          )}

          <div style={{ border: '1px solid var(--c-border)', borderRadius: 8, background: 'var(--c-bg)', padding: '10px 12px', margin: '10px 0' }}>
            <strong>Conséquences</strong>
            <ul style={{ margin: '6px 0 0', paddingLeft: 18 }}>
              <li>Pour chaque professeur, les lignes en brouillon passent en « soumis » en l'état. Le mois passe en « À valider ».</li>
              <li>Chaque professeur est traité séparément : si l'un échoue, les autres sont quand même soumis, et le résultat vous dit lesquels.</li>
              <li>Une version « soumise » est enregistrée au nom de la direction pour chacun ; chaque professeur est prévenu par notification et e-mail, avec votre message.</li>
            </ul>
          </div>

          <AdminFormField label="Message aux professeurs (obligatoire, 500 caractères max., commun à tous)" htmlFor="soumission-groupe-message">
            <AdminTextarea id="soumission-groupe-message" rows={3} maxLength={500} value={message} onChange={(e) => setMessage(e.target.value)} placeholder="Ex. : échéance de paie, heures soumises en l'état" />
          </AdminFormField>
        </>
      )}
    </AdminModal>
  );
}
