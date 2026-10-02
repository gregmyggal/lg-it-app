import { useEffect, useMemo, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminCheckbox, AdminInput } from '../AdminFormField';
import Banner from '../ui/Banner';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';
import { proposerLissage, validerLot } from '../../hooks/useTimesheets';
import { getErrorData, getErrorMessage } from '../../api/errors';
import { formatDateCourte } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';

/**
 * Validation en lot avec lissage (mock-up 03, Q21) : récapitulatif, puis, pour chaque jour qui dépasse le plafond,
 * l'aperçu du lissage proposé (montant et date cible modifiables) ; « Lisser puis valider » ou « Valider sans lisser ».
 * Aucune saisie n'est exclue du lot : le lissage fait partie du même flux et de la même transaction côté serveur.
 *
 * @param {object} props
 * @param {object[]} props.selection saisies soumises sélectionnées (`TimesheetResource`)
 * @param {object[]} props.toutesLesSaisies saisies du mois (pour détecter les dépassements par professeur et par jour)
 * @param {number} props.annee
 * @param {number} props.mois
 * @param {number} props.plafond plafond journalier (€) paramétré pour l'année (TS-00)
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onDone
 */
export default function ValiderLotModal({ selection, toutesLesSaisies, annee, mois, plafond: PLAFOND_JOURNALIER, onClose, onDone }) {
  const [propositions, setPropositions] = useState(null); // [{ cle, saisie, jour, depassement, date, montant, actif, note }]
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [invalides, setInvalides] = useState([]);

  // Jours (professeur × date) qui dépassent le plafond, d'après les montants de toutes les saisies du mois.
  const depassements = useMemo(() => {
    const parJour = new Map();
    toutesLesSaisies.forEach((t) => {
      const cle = `${t.professeur_id}|${t.date_prestation.slice(0, 10)}`;
      parJour.set(cle, (parJour.get(cle) || 0) + (Number(t.montant_brut) || 0));
    });
    return [...parJour.entries()]
      .filter(([, total]) => total > PLAFOND_JOURNALIER)
      .map(([cle, total]) => ({ cle, total }))
      .map((d) => ({ ...d, saisie: selection.find((t) => `${t.professeur_id}|${t.date_prestation.slice(0, 10)}` === d.cle) }))
      .filter((d) => d.saisie);
  }, [selection, toutesLesSaisies, PLAFOND_JOURNALIER]);

  useEffect(() => {
    let annule = false;
    Promise.all(
      depassements.map((d) =>
        proposerLissage(d.saisie.id, annee, mois)
          .then((p) => ({
            cle: d.cle,
            saisie: d.saisie,
            total: d.total,
            actif: Boolean(p.success && p.suggestion?.date_cible),
            date: p.suggestion?.date_cible || '',
            montant: String(p.suggestion?.quantite_a_deplacer_eur ?? Math.round((d.total - PLAFOND_JOURNALIER) * 100) / 100),
            note: p.suggestion?.note || null,
          }))
          .catch(() => ({ cle: d.cle, saisie: d.saisie, total: d.total, actif: false, date: '', montant: '', note: 'Aucune proposition de lissage disponible : ajustement manuel nécessaire.' })),
      ),
    ).then((liste) => !annule && setPropositions(liste));
    return () => {
      annule = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const totalHeures = selection.reduce((t, x) => t + Number(x.nombre_heures), 0);
  const totalMontant = selection.reduce((t, x) => t + (Number(x.montant_brut) || 0), 0);
  const lissagesActifs = (propositions || []).filter((p) => p.actif && p.date && Number(p.montant) > 0);

  function modifier(cle, patch) {
    setPropositions((prev) => prev.map((p) => (p.cle === cle ? { ...p, ...patch } : p)));
  }

  async function valider(avecLissage) {
    setEnvoi(true);
    setErreur(null);
    setInvalides([]);
    try {
      const r = await validerLot({
        ids: selection.map((t) => t.id),
        ...(avecLissage && lissagesActifs.length
          ? { lissages: lissagesActifs.map((p) => ({ timesheet_id: p.saisie.id, date_to: p.date, montant_to_move: Number(p.montant) })) }
          : {}),
      });
      onDone(
        `${r.validees} saisie${r.validees > 1 ? 's' : ''} validée${r.validees > 1 ? 's' : ''}${r.lissages_appliques ? `, ${r.lissages_appliques} lissage${r.lissages_appliques > 1 ? 's' : ''} appliqué${r.lissages_appliques > 1 ? 's' : ''}` : ''}.`,
      );
    } catch (err) {
      setErreur(getErrorMessage(err));
      setInvalides(getErrorData(err).invalides || []);
    } finally {
      setEnvoi(false);
    }
  }

  const peutLisser = lissagesActifs.length > 0;

  return (
    <AdminModal
      isOpen
      title="Valider les heures sélectionnées"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          {depassements.length > 0 && (
            <AdminButton variant="secondary" onClick={() => valider(false)} disabled={envoi || propositions === null}>
              Valider sans lisser
            </AdminButton>
          )}
          <AdminButton onClick={() => valider(true)} loading={envoi} disabled={envoi || propositions === null}>
            {peutLisser ? 'Lisser puis valider' : 'Valider les heures'}
          </AdminButton>
        </>
      }
    >
      {erreur && (
        <Banner tone="error" role="alert">
          <strong>{erreur}</strong>
          {invalides.length > 0 && (
            <ul style={{ margin: `${ADMIN_SPACING.sm} 0 0`, paddingLeft: '20px' }}>
              {invalides.map((i) => (
                <li key={i.id}>
                  Saisie n°{i.id} : {i.raison}
                </li>
              ))}
            </ul>
          )}
        </Banner>
      )}
      <p style={{ marginTop: 0 }}>
        <strong>
          {selection.length} saisie{selection.length > 1 ? 's' : ''}
        </strong>{' '}
        · {formatHeures(totalHeures)} · {formatEuros(totalMontant)}
      </p>

      {depassements.length === 0 && <p style={{ color: ADMIN_COLORS.textSecondary }}>Aucun jour ne dépasse le plafond journalier de {formatEuros(PLAFOND_JOURNALIER)} : aucun lissage nécessaire.</p>}
      {depassements.length > 0 && propositions === null && <p>Calcul des lissages proposés…</p>}
      {(propositions || []).map((p) => (
        <fieldset key={p.cle} style={{ border: `1px solid ${ADMIN_COLORS.border}`, borderRadius: '8px', padding: ADMIN_SPACING.lg, margin: `0 0 ${ADMIN_SPACING.lg}` }}>
          <legend style={{ fontWeight: 600, padding: '0 6px' }}>
            {p.saisie.professeur ? `${p.saisie.professeur.prenom} ${p.saisie.professeur.nom}` : 'Professeur'} · {formatDateCourte(p.saisie.date_prestation.slice(0, 10))}
          </legend>
          <p style={{ margin: `0 0 ${ADMIN_SPACING.sm}` }}>
            {formatEuros(p.total)} pour un plafond de {formatEuros(PLAFOND_JOURNALIER)} (dépassement : {formatEuros(p.total - PLAFOND_JOURNALIER)}).
          </p>
          <AdminCheckbox id={`lisser-${p.cle}`} label="Lisser ce jour" checked={p.actif} onChange={(e) => modifier(p.cle, { actif: e.target.checked })} />
          {p.actif && (
            <div style={{ display: 'flex', gap: ADMIN_SPACING.lg, marginTop: ADMIN_SPACING.md, flexWrap: 'wrap' }}>
              <label style={{ fontSize: '13px' }}>
                Montant à déplacer (€)
                <AdminInput type="number" step="0.01" min="0.01" max={PLAFOND_JOURNALIER} value={p.montant} onChange={(e) => modifier(p.cle, { montant: e.target.value })} />
              </label>
              <label style={{ fontSize: '13px' }}>
                Date cible
                <AdminInput type="date" value={p.date} onChange={(e) => modifier(p.cle, { date: e.target.value })} />
              </label>
            </div>
          )}
          {p.note && <p style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary, marginBottom: 0 }}>{p.note}</p>}
        </fieldset>
      ))}
      <p style={{ fontSize: '13px', marginBottom: 0 }}>
        La validation et les lissages sont appliqués ensemble : si une saisie ne peut pas être validée, rien n'est modifié.
      </p>
    </AdminModal>
  );
}
