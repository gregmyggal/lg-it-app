import { useEffect, useMemo, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect, AdminCheckbox } from '../AdminFormField';
import Banner from '../ui/Banner';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';
import { annulerSession, apercuDeplacement, creerBis, deplacerSession } from '../../hooks/useClasses';
import DeplacementApercu from './DeplacementApercu';
import { getErrorData, getErrorMessage, getFieldErrors, getStatus } from '../../api/errors';
import { formatDate, formatDateLongue, nomJour } from '../../utils/dates';
import { libelleSession, libelleSessionPhrase } from '../../utils/classes';

const MODES = {
  deplacer: 'Déplacer à une autre date',
  annuler: 'Annuler la séance (motif obligatoire)',
  bis: "Ajouter un bis d'une séance",
};

/**
 * Ajustement d'une session : déplacer, annuler (motif obligatoire) ou ajouter un bis (mock-up 03).
 * Les actions proposées viennent de `session.can` ; les erreurs 409/422 de l'API sont affichées telles quelles.
 * Une date après la fin de la période n'est jamais bloquée : avertissement (séance « hors période »).
 * Le dépassement du nombre de sessions (409) demande une confirmation explicite avant de renvoyer la demande.
 *
 * @param {object} props
 * @param {object} props.classe ClasseResource
 * @param {object[]} props.sessions toutes les sessions de la classe (pour lister les séances d'un bis)
 * @param {object|null} props.session session ajustée ; `null` = ajout d'un bis sans session de départ
 * @param {'deplacer'|'annuler'|'bis'} [props.modeInitial]
 * @param {number} [props.periodeInitiale] période présélectionnée pour un bis sans session de départ
 * @param {() => void} props.onClose
 * @param {(message: string, avertissements?: string[]) => void} props.onDone appelée après succès, avec le message de confirmation
 *   et les avertissements non bloquants du serveur (ex. date après la fin de la période)
 */
export default function SessionAdjustModal({ classe, sessions, session, modeInitial, periodeInitiale, onClose, onDone }) {
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
  const [seance, setSeance] = useState(session ? `${session.periode_numero || 1}-${session.seance_numero}` : '');
  const [depassement, setDepassement] = useState(null); // { message }
  const [confirme, setConfirme] = useState(false);
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  // Séances proposables pour un bis : « P1 · Séance 3 » (période + numéro, sans les bis).
  const seances = useMemo(() => {
    const vues = new Map();
    sessions.forEach((s) => {
      const cle = `${s.periode_numero || 1}-${s.seance_numero}`;
      if (!vues.has(cle)) vues.set(cle, { cle, periode: s.periode_numero || 1, numero: s.seance_numero });
    });
    return [...vues.values()].sort((a, b) => a.periode - b.periode || a.numero - b.numero);
  }, [sessions]);
  const modeBisSeul = modesDispo.length === 1;
  const seanceEffective = seance || seances.find((x) => x.periode === periodeInitiale)?.cle || seances[0]?.cle || '';
  const [periodeBis, numeroBis] = seanceEffective.split('-').map(Number);
  // Période concernée par la date saisie : celle de la session déplacée, ou celle de la séance du bis.
  const numeroPeriode = mode === 'bis' ? periodeBis : session?.periode_numero || 1;
  const periode = (classe.periodes || []).find((p) => p.numero === numeroPeriode)?.periode || null;
  const horsPeriode = Boolean(periode && date && date > periode.date_fin && mode !== 'annuler');

  // CLS-06 : décalage des séances suivantes (une par semaine, congés sautés, numéros conservés).
  const numeroSession = session?.periode_numero || 1;
  const suivantes = useMemo(
    () => (session ? sessions.filter((s) => (s.periode_numero || 1) === numeroSession && s.bis_rang === 0 && s.seance_numero > session.seance_numero) : []),
    [sessions, session, numeroSession],
  );
  const premierePeriodeSuivante = useMemo(
    () => sessions.filter((s) => (s.periode_numero || 1) > numeroSession && s.statut !== 'annulee').map((s) => s.date).sort()[0],
    [sessions, numeroSession],
  );
  const decalable = Boolean(session && session.bis_rang === 0 && (suivantes.length > 0 || (premierePeriodeSuivante && date >= premierePeriodeSuivante)));
  const [decaler, setDecaler] = useState(true);
  const [datesForcees, setDatesForcees] = useState([]);
  const [apercu, setApercu] = useState({ data: null, loading: false });
  const [versionApercu, setVersionApercu] = useState(0);
  const avecDecalage = mode === 'deplacer' && decalable && decaler;

  useEffect(() => {
    if (!avecDecalage || !date) {
      setApercu({ data: null, loading: false });
      return undefined;
    }
    let annule = false;
    setApercu((prev) => ({ ...prev, loading: true }));
    const minuteur = setTimeout(() => {
      apercuDeplacement(session.id, { date, heure_debut: heureDebut, heure_fin: heureFin, dates_forcees: datesForcees })
        .then((data) => !annule && setApercu({ data, loading: false }))
        .catch((err) => {
          if (annule) return;
          setApercu({ data: null, loading: false });
          setErreurs(getFieldErrors(err));
          setMessage(getErrorMessage(err));
        });
    }, 300);
    return () => {
      annule = true;
      clearTimeout(minuteur);
    };
  }, [avecDecalage, session?.id, date, heureDebut, heureFin, datesForcees, versionApercu]);

  function forcerDate(d, forcer) {
    setDatesForcees((prev) => (forcer ? [...prev.filter((x) => x !== d), d].sort() : prev.filter((x) => x !== d)));
  }

  const blocs = apercu.data?.periodes || [];
  const nbPropre = (blocs[0]?.decalees || 0) + 1;
  const nbCascade = blocs[1]?.decalees || 0;
  const nbSuivantes = nbPropre - 1 + nbCascade;

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
      ? `Remplacer la ${libelleSessionPhrase(session)} (${formatDate(session.date)})`
      : `Ajuster la ${libelleSessionPhrase(session)} — ${formatDateLongue(session.date)}`;

  const valide =
    (mode === 'deplacer' && Boolean(date) && (!avecDecalage || (Boolean(apercu.data) && !apercu.loading))) ||
    (mode === 'annuler' && motif.trim().length > 0) ||
    (mode === 'bis' && Boolean(date) && Boolean(seanceEffective) && (!depassement || confirme));

  const libelleBouton =
    mode === 'deplacer'
      ? avecDecalage && nbCascade > 0
        ? `Déplacer ${nbPropre} séances (P${numeroSession}) + ${nbCascade} (P${numeroSession + 1})`
        : avecDecalage && nbPropre > 1
          ? `Déplacer ${nbPropre} séances`
          : 'Déplacer la séance'
      : mode === 'annuler' ? `Annuler la séance ${session ? libelleSession(session) : ''}` : 'Créer le bis';

  async function soumettre(e) {
    e.preventDefault();
    if (!valide) return;
    setEnvoi(true);
    setMessage(null);
    setErreurs({});
    try {
      if (mode === 'deplacer') {
        const payload = { date, heure_debut: heureDebut, heure_fin: heureFin };
        if (avecDecalage) Object.assign(payload, { decaler_suivantes: true, dates_forcees: datesForcees, empreinte: apercu.data.empreinte });
        const maj = await deplacerSession(session.id, payload);
        if (maj.replanification) {
          onDone(messageDecalage(maj, maj.replanification, numeroSession), maj.replanification.avertissements);
        } else {
          onDone(`${libelleSession(maj)} déplacée au ${formatDate(maj.date)}. Elle garde son numéro de séance et sa période.`, maj.avertissements);
        }
      } else if (mode === 'annuler') {
        await annulerSession(session.id, motif.trim());
        onDone(`${libelleSession(session)} annulée (motif : ${motif.trim()}). Elle garde son numéro et reste visible avec son motif.`);
      } else {
        const bis = await creerBis(classe.id, {
          seance_numero: numeroBis,
          periode_numero: periodeBis,
          date,
          heure_debut: heureDebut,
          heure_fin: heureFin,
          confirmer_depassement: Boolean(depassement && confirme),
        });
        onDone(`${libelleSession(bis)} créée le ${formatDate(bis.date)}. Les autres sessions de la classe restent inchangées.`, bis.avertissements);
      }
    } catch (err) {
      if (mode === 'deplacer' && getStatus(err) === 409 && getErrorData(err).code === 'planning_modifie') {
        setMessage(getErrorMessage(err));
        setVersionApercu((v) => v + 1);
      } else if (mode === 'bis' && getStatus(err) === 409 && getErrorData(err).nb_sessions) {
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
    ? `La période ${periode.numero} se termine le ${formatDate(periode.date_fin)}. Une date plus tardive reste possible (séance « hors période »).`
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
            <AvertissementHorsPeriode actif={horsPeriode} periode={periode} />
            {decalable && (
              <div style={{ margin: `${ADMIN_SPACING.lg} 0 ${ADMIN_SPACING.sm}` }}>
                <AdminCheckbox
                  id="ajust-decaler"
                  label={
                    suivantes.length > 0
                      ? `Décaler aussi les séances suivantes (P${numeroSession} · ${session.seance_numero + 1} à ${Math.max(...suivantes.map((s) => s.seance_numero))})`
                      : `Décaler aussi la période ${numeroSession + 1} si nécessaire`
                  }
                  checked={decaler}
                  onChange={(e) => modifier(setDecaler)(e.target.checked)}
                />
                <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginTop: ADMIN_SPACING.xs, marginLeft: '28px' }}>
                  Une séance par semaine, le {nomJour(classe.jour_semaine)}, en sautant les congés. Les numéros de séance ne changent pas.
                </div>
              </div>
            )}
            {avecDecalage && date && <DeplacementApercu apercu={apercu.data} chargement={apercu.loading} datesForcees={datesForcees} onForcer={forcerDate} />}
            <Consequence>
              {avecDecalage && nbSuivantes > 0 ? (
                <>
                  La {libelleSessionPhrase(session)} passe au {formatDate(date)} et{' '}
                  <strong>
                    {nbSuivantes === 1 ? 'la séance suivante est décalée' : `les ${nbSuivantes} séances suivantes sont décalées`}
                  </strong>
                  . Toutes gardent leur numéro de séance, leur horaire, leur lieu et leurs professeurs.
                </>
              ) : (
                <>
                  La {libelleSessionPhrase(session)} est déplacée{date ? ` au ${formatDate(date)}` : ''} et{' '}
                  <strong>garde son numéro de séance {session.seance_numero}</strong> et sa période.
                </>
              )}
            </Consequence>
          </>
        )}

        {mode === 'annuler' && (
          <>
            <AdminFormField label="Motif de l'annulation (obligatoire)" htmlFor="ajust-motif" required error={erreurs.motif_annulation}>
              <AdminInput id="ajust-motif" value={motif} onChange={(e) => modifier(setMotif)(e.target.value)} error={erreurs.motif_annulation} />
            </AdminFormField>
            <Consequence>
              La {libelleSessionPhrase(session)} devient <strong>« {libelleSession(session)} — Annulée »</strong> : elle{' '}
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
                options={seances.map((x) => ({ value: x.cle, label: `P${x.periode} · Séance ${x.numero}` }))}
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
              aide={`Une session ajoutée est toujours rattachée à l'une des séances : « P${periodeBis} · Séance ${numeroBis} bis ». ${aidePeriode}`}
              erreurs={erreurs}
            />
            <AvertissementHorsPeriode actif={horsPeriode} periode={periode} />
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

const MOTIFS = { heures_encodees: 'heures encodées', terminee: 'terminée' };

function messageDecalage(session, rep, numero) {
  const propres = rep.decalees[`p${numero}`] || 0;
  const cascade = rep.decalees[`p${numero + 1}`] || 0;
  const derniere = rep.periodes
    .flatMap((b) => b.lignes)
    .filter((l) => l.etat === 'decalee' || l.etat === 'deplacee')
    .map((l) => l.date_apres)
    .sort()
    .at(-1);
  const arret = rep.periodes.find((b) => b.arret)?.arret;
  const decalees = cascade
    ? `${propres} séance${propres > 1 ? 's' : ''} suivante${propres > 1 ? 's' : ''} de la période ${numero} et ${cascade} séance${cascade > 1 ? 's' : ''} de la période ${numero + 1} décalées`
    : `${propres} séance${propres > 1 ? 's' : ''} suivante${propres > 1 ? 's' : ''} décalée${propres > 1 ? 's' : ''}`;
  return `${libelleSession(session)} déplacée au ${formatDate(session.date)}. ${decalees}${derniere ? ` (dernière le ${formatDate(derniere)})` : ''}${
    arret ? ` ; arrêt à la ${arret.seance} (${MOTIFS[arret.motif]}), elle et les suivantes sont inchangées` : ''
  }. Les numéros de séance sont inchangés.`;
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

function AvertissementHorsPeriode({ actif, periode }) {
  if (!actif) return null;
  return (
    <Banner tone="warning" role="status">
      <strong>⚠ Après la fin de la période {periode.numero} ({formatDate(periode.date_fin)}) : non bloquant.</strong> La séance sera marquée « Hors période · rattrapage ».
    </Banner>
  );
}
