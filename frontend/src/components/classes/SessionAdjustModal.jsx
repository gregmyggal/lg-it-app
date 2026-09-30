import { useMemo, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect, AdminCheckbox } from '../AdminFormField';
import Banner from '../ui/Banner';
import { ADMIN_SPACING, ADMIN_TONES, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';
import { annulerSession, creerBis, deplacerSession } from '../../hooks/useClasses';
import { getErrorData, getErrorMessage, getFieldErrors, getStatus } from '../../api/errors';
import { formatDate, formatDateLongue } from '../../utils/dates';

const MODES = {
  deplacer: 'Déplacer à une autre date',
  annuler: 'Annuler la séance (motif obligatoire)',
  bis: "Ajouter un bis d'une séance",
};

/**
 * Ajustement d'une session : déplacer, annuler (motif obligatoire) ou ajouter un bis (mock-up 03).
 * Les actions proposées viennent de `session.can` ; les erreurs 409/422 de l'API sont affichées telles quelles.
 * Le dépassement du nombre de sessions (409) demande une confirmation explicite avant de renvoyer la demande.
 *
 * @param {object} props
 * @param {object} props.classe ClasseResource
 * @param {object[]} props.sessions toutes les sessions de la classe (pour lister les séances d'un bis)
 * @param {object|null} props.session session ajustée ; `null` = ajout d'un bis sans session de départ
 * @param {'deplacer'|'annuler'|'bis'} [props.modeInitial]
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onDone appelée après succès, avec le message de confirmation
 */
export default function SessionAdjustModal({ classe, sessions, session, modeInitial, onClose, onDone }) {
  const modesDispo = useMemo(() => {
    const liste = [];
    if (session?.can?.update) liste.push('deplacer');
    if (session?.can?.cancel) liste.push('annuler');
    liste.push('bis');
    return liste;
  }, [session]);

  const [mode, setMode] = useState(modesDispo.includes(modeInitial) ? modeInitial : modesDispo[0]);
  const [date, setDate] = useState(mode === 'bis' ? '' : session?.date || '');
  const [heureDebut, setHeureDebut] = useState(session?.heure_debut || classe.heure_debut);
  const [heureFin, setHeureFin] = useState(session?.heure_fin || classe.heure_fin);
  const [motif, setMotif] = useState('');
  const [seance, setSeance] = useState(String(session?.seance_numero || ''));
  const [depassement, setDepassement] = useState(null); // { message }
  const [confirme, setConfirme] = useState(false);
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  const numeros = useMemo(
    () => [...new Set(sessions.map((s) => s.seance_numero))].sort((a, b) => a - b),
    [sessions],
  );
  const periode = classe.periode;
  const modeBisSeul = modesDispo.length === 1;
  const seanceEffective = seance || String(numeros[0] || '');

  function changerMode(nouveau) {
    setMode(nouveau);
    setErreurs({});
    setMessage(null);
    setDepassement(null);
    setConfirme(false);
    setDate(nouveau === 'bis' ? '' : session?.date || '');
  }

  function modifier(setter) {
    return (valeur) => {
      setter(valeur);
      setErreurs({});
      setMessage(null);
    };
  }

  const titre = !session
    ? 'Ajouter un bis à la classe'
    : mode === 'bis'
      ? `Remplacer la ${session.libelle.toLowerCase()} (${formatDate(session.date)})`
      : `Ajuster la ${session.libelle.toLowerCase()} — ${formatDateLongue(session.date)}`;

  const valide =
    (mode === 'deplacer' && Boolean(date)) ||
    (mode === 'annuler' && motif.trim().length > 0) ||
    (mode === 'bis' && Boolean(date) && Boolean(seanceEffective) && (!depassement || confirme));

  const libelleBouton =
    mode === 'deplacer' ? 'Déplacer la séance' : mode === 'annuler' ? `Annuler la séance ${session?.seance_numero}` : 'Créer le bis';

  async function soumettre(e) {
    e.preventDefault();
    if (!valide) return;
    setEnvoi(true);
    setMessage(null);
    setErreurs({});
    try {
      if (mode === 'deplacer') {
        const maj = await deplacerSession(session.id, { date, heure_debut: heureDebut, heure_fin: heureFin });
        onDone(`${maj.libelle} déplacée au ${formatDate(maj.date)}. Elle garde son numéro de séance.`);
      } else if (mode === 'annuler') {
        await annulerSession(session.id, motif.trim());
        onDone(`${session.libelle} annulée (motif : ${motif.trim()}). Elle garde son numéro et reste visible avec son motif.`);
      } else {
        const bis = await creerBis(classe.id, {
          seance_numero: Number(seanceEffective),
          date,
          heure_debut: heureDebut,
          heure_fin: heureFin,
          confirmer_depassement: Boolean(depassement && confirme),
        });
        onDone(`${bis.libelle} créée le ${formatDate(bis.date)}. Les autres sessions de la classe restent inchangées.`);
      }
    } catch (err) {
      if (mode === 'bis' && getStatus(err) === 409 && getErrorData(err).nb_sessions) {
        setDepassement({ message: getErrorMessage(err) });
      } else {
        setErreurs(getFieldErrors(err));
        setMessage(getErrorMessage(err));
      }
    } finally {
      setEnvoi(false);
    }
  }

  const aidePeriode = periode
    ? `La période ${periode.numero} se termine le ${formatDate(periode.date_fin)}.`
    : '';

  return (
    <AdminModal
      isOpen
      title={titre}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Fermer sans modifier
          </AdminButton>
          <AdminButton
            type="submit"
            form="form-ajustement"
            variant={mode === 'annuler' ? 'danger' : 'primary'}
            disabled={!valide || envoi}
            loading={envoi}
          >
            {libelleBouton}
          </AdminButton>
        </>
      }
    >
      <form id="form-ajustement" onSubmit={soumettre} noValidate>
        {message && Object.keys(erreurs).length === 0 && (
          <Banner tone="error">
            <strong>{message}</strong>
          </Banner>
        )}

        {!modeBisSeul && (
          <fieldset style={{ border: 0, padding: 0, margin: `0 0 ${ADMIN_SPACING.lg}` }}>
            <legend style={{ fontSize: '12px', fontWeight: 600, textTransform: 'uppercase', marginBottom: ADMIN_SPACING.sm }}>
              Que voulez-vous faire ?
            </legend>
            <div style={{ display: 'flex', flexDirection: 'column', gap: ADMIN_SPACING.sm }}>
              {modesDispo.map((m) => (
                <label key={m} style={{ display: 'flex', alignItems: 'center', gap: ADMIN_SPACING.sm, fontSize: '14px' }}>
                  <input type="radio" name="ajustement" checked={mode === m} onChange={() => changerMode(m)} />
                  {MODES[m]}
                </label>
              ))}
            </div>
          </fieldset>
        )}

        {mode === 'deplacer' && (
          <>
            <DateEtHoraire
              date={date}
              setDate={modifier(setDate)}
              heureDebut={heureDebut}
              setHeureDebut={modifier(setHeureDebut)}
              heureFin={heureFin}
              setHeureFin={modifier(setHeureFin)}
              labelDate="Nouvelle date"
              aide={aidePeriode}
              erreurs={erreurs}
            />
            <Consequence>
              La {session.libelle.toLowerCase()} est déplacée{date ? ` au ${formatDate(date)}` : ''} et{' '}
              <strong>garde son numéro de séance {session.seance_numero}</strong>.
            </Consequence>
          </>
        )}

        {mode === 'annuler' && (
          <>
            <AdminFormField label="Motif de l'annulation (obligatoire)" htmlFor="ajust-motif" required error={erreurs.motif_annulation}>
              <AdminInput id="ajust-motif" value={motif} onChange={(e) => modifier(setMotif)(e.target.value)} error={erreurs.motif_annulation} />
            </AdminFormField>
            <Consequence>
              La {session.libelle.toLowerCase()} devient <strong>« {session.libelle} — Annulée »</strong> : elle{' '}
              <strong>garde son numéro</strong> et reste visible avec son motif. Vous pourrez ensuite la remplacer par un bis à une autre date de la période.
            </Consequence>
          </>
        )}

        {mode === 'bis' && (
          <>
            <AdminFormField label="Bis de la séance" htmlFor="ajust-seance" required error={erreurs.seance_numero}>
              <AdminSelect
                id="ajust-seance"
                value={seanceEffective}
                options={numeros.map((n) => ({ value: String(n), label: `Séance ${n}` }))}
                onChange={(e) => modifier(setSeance)(e.target.value)}
                error={erreurs.seance_numero}
              />
            </AdminFormField>
            <DateEtHoraire
              date={date}
              setDate={modifier(setDate)}
              heureDebut={heureDebut}
              setHeureDebut={modifier(setHeureDebut)}
              heureFin={heureFin}
              setHeureFin={modifier(setHeureFin)}
              labelDate="Date du bis"
              aide={`Une session ajoutée est toujours rattachée à l'une des séances : « Séance ${seanceEffective} bis ». ${aidePeriode}`}
              erreurs={erreurs}
            />
            {depassement && (
              <div
                role="alert"
                style={{
                  background: ADMIN_TONES.warning.bg,
                  color: ADMIN_TONES.warning.fg,
                  border: `1px solid ${ADMIN_TONES.warning.border}`,
                  borderRadius: ADMIN_RADIUS.md,
                  padding: ADMIN_SPACING.lg,
                }}
              >
                <strong>{depassement.message}.</strong> (Le total de sessions peut dépasser 14, jamais le nombre de séances.)
                <div style={{ marginTop: ADMIN_SPACING.sm }}>
                  <AdminCheckbox
                    id="ajust-confirmer"
                    label="Je confirme le dépassement de 14 sessions"
                    checked={confirme}
                    onChange={(e) => setConfirme(e.target.checked)}
                  />
                </div>
              </div>
            )}
          </>
        )}
      </form>
    </AdminModal>
  );
}

function DateEtHoraire({ date, setDate, heureDebut, setHeureDebut, heureFin, setHeureFin, labelDate, aide, erreurs }) {
  return (
    <>
      <AdminFormField label={labelDate} htmlFor="ajust-date" required error={erreurs.date} description={aide}>
        <AdminInput id="ajust-date" type="date" value={date} onChange={(e) => setDate(e.target.value)} error={erreurs.date} />
      </AdminFormField>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: ADMIN_SPACING.lg }}>
        <AdminFormField label="Début" htmlFor="ajust-debut" error={erreurs.heure_debut}>
          <AdminInput id="ajust-debut" type="time" value={heureDebut} onChange={(e) => setHeureDebut(e.target.value)} error={erreurs.heure_debut} />
        </AdminFormField>
        <AdminFormField label="Fin" htmlFor="ajust-fin" error={erreurs.heure_fin}>
          <AdminInput id="ajust-fin" type="time" value={heureFin} onChange={(e) => setHeureFin(e.target.value)} error={erreurs.heure_fin} />
        </AdminFormField>
      </div>
    </>
  );
}

function Consequence({ children }) {
  return (
    <div
      role="status"
      aria-live="polite"
      style={{
        background: ADMIN_TONES.primary.bg,
        color: ADMIN_TONES.primary.fg,
        border: `1px solid ${ADMIN_TONES.primary.border}`,
        borderRadius: ADMIN_RADIUS.md,
        padding: ADMIN_SPACING.lg,
        fontSize: '14px',
      }}
    >
      <strong>Conséquence</strong>
      <div style={{ marginTop: ADMIN_SPACING.xs }}>{children}</div>
    </div>
  );
}
