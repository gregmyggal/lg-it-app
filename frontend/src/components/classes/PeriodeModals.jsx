import { useEffect, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect } from '../AdminFormField';
import Banner from '../ui/Banner';
import LinkButton from '../ui/LinkButton';
import ClasseApercu from './ClasseApercu';
import LienModifierDates from '../annees/LienModifierDates';
import { useAnneesScolaires } from '../../hooks/useAnneesScolaires';
import { useCours } from '../../hooks/useCours';
import { LoadingBlock, ErrorBlock } from '../ui/DataStates';
import { ajouterPeriode, annulerPeriode, apercuPeriode, changerCoursPeriode, supprimerPeriode, useHistoriqueCoursPeriode } from '../../hooks/useClasses';
import { contexteHorsBornes, sauverBrouillon } from '../../utils/annees';
import { getErrorData, getErrorMessage, getFieldErrors, getStatus } from '../../api/errors';
import { addDays, formatDate, jourDeClasseApres, libelleCreneau, nomJour, parseDate, toISODate } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

/** Modale « Ajouter la période N » : cours, date de démarrage, aperçu des 14 séances, professeurs hérités (mock-up CLS-02/02). */
export function AjouterPeriodeModal({ classe, numero, dateConseillee, coursIdInitial, onClose, onDone }) {
  const annees = useAnneesScolaires();
  const cours = useCours();
  const [coursId, setCoursId] = useState(coursIdInitial ? String(coursIdInitial) : '');
  const [date, setDate] = useState(dateConseillee || '');
  const [apercu, setApercu] = useState({ data: null, loading: false, erreurs: {}, message: null, contexte: null });
  const [contexteSoumission, setContexteSoumission] = useState(null);
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const [datesForcees, setDatesForcees] = useState([]);

  const annee = (annees.data || []).find((a) => a.id === classe.annee_scolaire_id);
  const periodeAnnee = annee?.periodes.find((p) => p.numero === numero);
  const profs = (classe.professeurs || []).filter((p) => p.actif !== false);
  const pret = Boolean(coursId && date && periodeAnnee);

  // La date conseillée peut précéder le début de la période choisie : on la recale sur le 1er jour de classe de la période.
  const debutPeriode = periodeAnnee?.date_debut;
  useEffect(() => {
    if (!debutPeriode) return;
    setDate((actuelle) => {
      if (actuelle && actuelle >= debutPeriode) return actuelle;
      const veille = toISODate(addDays(parseDate(debutPeriode), -1));
      return jourDeClasseApres(veille, null, classe.jour_semaine) || actuelle;
    });
  }, [debutPeriode, classe.jour_semaine]);

  useEffect(() => {
    if (!pret) {
      setApercu({ data: null, loading: false, erreurs: {}, message: null, contexte: null });
      return undefined;
    }
    let annule = false;
    setApercu((prev) => ({ ...prev, loading: true }));
    const t = setTimeout(() => {
      apercuPeriode(classe.id, { periode_id: periodeAnnee.id, cours_id: Number(coursId), date_premiere_session: date, dates_forcees: datesForcees })
        .then((data) => !annule && setApercu({ data, loading: false, erreurs: {}, message: null, contexte: null }))
        .catch((err) => !annule && setApercu({ data: null, loading: false, erreurs: getFieldErrors(err), message: getErrorMessage(err), contexte: contexteHorsBornes(getErrorData(err)) }));
    }, 400);
    return () => {
      annule = true;
      clearTimeout(t);
    };
  }, [pret, classe.id, periodeAnnee, coursId, date, datesForcees]);

  const plan = apercu.data?.periodes?.[0];
  const blocage = plan?.blocage || null;
  const messageDate = apercu.erreurs.date_premiere_session || erreurs.date_premiere_session || blocage?.message;
  const contexteBornes = apercu.contexte || contexteSoumission;
  const sauverSaisie = () => sauverBrouillon(`periode-${classe.id}`, { numero, coursId, date });
  // Lien contextuel : fermeture de la modale (changement de page), saisie conservée puis restaurée au retour.
  const erreurDate =
    messageDate && contexteBornes && annee?.can?.update ? (
      <>
        {messageDate}{' '}
        <LienModifierDates annee={annee} numero={numero} retour={`/admin/classes/${classe.id}?restaurer_periode=1`} avantNavigation={sauverSaisie}>
          Modifier les dates de la période {numero} ({contexteBornes.annee_libelle || annee.libelle})
        </LienModifierDates>
      </>
    ) : (
      messageDate
    );

  async function soumettre(e) {
    e.preventDefault();
    if (!pret || blocage) return;
    setEnvoi(true);
    setMessage(null);
    setErreurs({});
    try {
      const maj = await ajouterPeriode(classe.id, { periode_id: periodeAnnee.id, cours_id: Number(coursId), date_premiere_session: date, dates_forcees: datesForcees });
      const p = maj.periodes?.find((x) => x.numero === numero);
      onDone(
        `Période ${numero} ajoutée : ${p?.cours?.titre || ''}, ${p?.nb_sessions ?? 14} séances du ${formatDate(p?.date_premiere_session || date)} au ${formatDate(p?.date_derniere_session)}. Les professeurs de la classe y sont assignés.`,
      );
    } catch (err) {
      setErreurs(getFieldErrors(err));
      setContexteSoumission(contexteHorsBornes(getErrorData(err)));
      setMessage(getErrorMessage(err, "La période n'a pas pu être ajoutée."));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      size="lg"
      title={`Ajouter la période ${numero}`}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton type="submit" form="form-ajout-periode" disabled={!pret || Boolean(blocage) || apercu.loading || !plan} loading={envoi}>
            {plan?.avertissements?.length ? `Ajouter quand même la période ${numero} (14 séances)` : `Ajouter la période ${numero} (14 séances)`}
          </AdminButton>
        </>
      }
    >
      <form id="form-ajout-periode" onSubmit={soumettre} noValidate>
        {message && Object.keys(erreurs).length === 0 && (
          <Banner tone="error">
            <strong>{message}</strong>
          </Banner>
        )}
        {cours.error && <Banner tone="error">Impossible de charger les cours. Fermez puis rouvrez la fenêtre.</Banner>}
        <AdminFormField label={`Cours (P${numero})`} htmlFor="periode-cours" required error={erreurs.cours_id}>
          <AdminSelect
            id="periode-cours"
            value={coursId}
            placeholder="Choisir un cours"
            options={(cours.data || []).map((c) => ({ value: String(c.id), label: c.titre }))}
            onChange={(e) => setCoursId(e.target.value)}
            error={erreurs.cours_id}
          />
        </AdminFormField>
        <AdminFormField
          label={`Date de démarrage de la période ${numero}`}
          htmlFor="periode-date"
          required
          error={erreurDate}
          description={`${nomJour(classe.jour_semaine)} (jour de la classe).${periodeAnnee ? ` Période ${numero} : ${formatDate(periodeAnnee.date_debut)} → ${formatDate(periodeAnnee.date_fin)}.` : ''}`}
        >
          <AdminInput
            id="periode-date"
            type="date"
            value={date}
            min={periodeAnnee?.date_debut}
            max={periodeAnnee?.date_fin}
            onChange={(e) => setDate(e.target.value)}
            error={erreurDate}
          />
        </AdminFormField>
        {apercu.message && !apercu.data && Object.keys(apercu.erreurs).length === 0 && <Banner tone="error">{apercu.message}</Banner>}
        <ClasseApercu
          apercu={apercu.data}
          chargement={apercu.loading}
          titre="Aperçu des 14 séances"
          onForcer={(_, d, forcer) => setDatesForcees((prev) => (forcer ? [...prev.filter((x) => x !== d), d].sort() : prev.filter((x) => x !== d)))}
        />
        <p style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary, marginBottom: ADMIN_SPACING.sm }}>
          Mêmes jour, horaire, lieu : {libelleCreneau(classe)}
          {classe.lieu ? `, ${classe.lieu}` : ''}.
          {profs.length > 0 ? ` Professeurs assignés aux 14 nouvelles séances : ${profs.map((p) => `${p.prenom || ''} ${p.nom || ''}`.trim()).join(', ')}.` : ''}
        </p>
      </form>
    </AdminModal>
  );
}

/** Modale « Changer le cours de la période N » : correction rétroactive tant qu'aucune heure n'est encodée (409 sinon). */
export function ChangerCoursModal({ classe, periode, onClose, onDone }) {
  const cours = useCours();
  const [coursId, setCoursId] = useState(String(periode.cours_id));
  const [envoi, setEnvoi] = useState(false);
  const [refus, setRefus] = useState(null); // { message, nb_saisies }
  const [message, setMessage] = useState(null);

  async function soumettre(e) {
    e.preventDefault();
    setEnvoi(true);
    setMessage(null);
    try {
      await changerCoursPeriode(classe.id, periode.id, Number(coursId));
      const titre = (cours.data || []).find((c) => String(c.id) === coursId)?.titre;
      onDone(`Cours de la période ${periode.numero} remplacé par « ${titre} ».`);
    } catch (err) {
      if (getStatus(err) === 409) {
        setRefus({ message: getErrorMessage(err), nb_saisies: getErrorData(err).nb_saisies });
      } else {
        setMessage(getErrorMessage(err, "Le cours n'a pas pu être changé."));
      }
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      size="md"
      title={`Changer le cours de la période ${periode.numero}`}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton
            type="submit"
            form="form-changer-cours"
            disabled={Boolean(refus) || !coursId || coursId === String(periode.cours_id)}
            loading={envoi}
          >
            Changer le cours
          </AdminButton>
        </>
      }
    >
      <form id="form-changer-cours" onSubmit={soumettre} noValidate>
        {message && (
          <Banner tone="error">
            <strong>{message}</strong>
          </Banner>
        )}
        {refus && (
          <Banner
            tone="error"
            actions={
              <LinkButton to="/admin/timesheets" size="sm">
                Voir les timesheets concernées
              </LinkButton>
            }
          >
            <strong>
              {refus.message}
              {refus.nb_saisies ? ` (${refus.nb_saisies} saisie${refus.nb_saisies > 1 ? 's' : ''})` : ''}
            </strong>{' '}
            Le cours ne peut plus être changé.
          </Banner>
        )}
        <AdminFormField label="Nouveau cours" htmlFor="changer-cours" required description="Correction rétroactive possible, y compris sur les séances déjà passées.">
          <AdminSelect
            id="changer-cours"
            value={coursId}
            options={(cours.data || []).map((c) => ({ value: String(c.id), label: c.titre }))}
            onChange={(e) => setCoursId(e.target.value)}
          />
        </AdminFormField>
        <strong>Conséquences</strong>
        <ul style={{ margin: `${ADMIN_SPACING.sm} 0 0`, paddingLeft: '20px', fontSize: '14px' }}>
          <li>Les séances de la période {periode.numero} (passées et à venir) prennent le nouveau cours ; liens généraux et liens « Séance n » remplacés.</li>
          <li>Les autres périodes ne sont pas modifiées. Si les deux périodes ont le même cours, leurs liens par séance sont partagés.</li>
          <li>Bloqué dès que des heures sont encodées sur la période.</li>
        </ul>
      </form>
    </AdminModal>
  );
}

/**
 * Modale « Supprimer ou annuler la période N » : suppression si aucune heure encodée ; sinon (409) bascule sur
 * l'annulation avec motif obligatoire (séances à venir annulées, séances passées et heures conservées).
 */
export function SupprimerPeriodeModal({ classe, periode, onClose, onDone }) {
  const [etape, setEtape] = useState('choix'); // 'choix' | 'annulation'
  const [motif, setMotif] = useState('');
  const [raison, setRaison] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const [message, setMessage] = useState(null);

  async function supprimer() {
    setEnvoi(true);
    setMessage(null);
    try {
      await supprimerPeriode(classe.id, periode.id);
      onDone(`Période ${periode.numero} supprimée avec ses séances. Vous pouvez en ajouter une nouvelle plus tard.`);
    } catch (err) {
      if (getStatus(err) === 409) {
        setRaison(getErrorMessage(err));
        setEtape('annulation');
      } else {
        setMessage(getErrorMessage(err, "La période n'a pas pu être supprimée."));
      }
    } finally {
      setEnvoi(false);
    }
  }

  async function annuler(e) {
    e.preventDefault();
    if (!motif.trim()) return;
    setEnvoi(true);
    setMessage(null);
    try {
      await annulerPeriode(classe.id, periode.id, motif.trim());
      onDone(`Période ${periode.numero} annulée (motif : ${motif.trim()}). Les séances à venir sont annulées, les séances passées et leurs heures sont conservées.`);
    } catch (err) {
      setMessage(getErrorMessage(err, "La période n'a pas pu être annulée."));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      size="md"
      title={`Supprimer ou annuler la période ${periode.numero}`}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Retour
          </AdminButton>
          {etape === 'choix' ? (
            <>
              <AdminButton variant="secondary" onClick={() => setEtape('annulation')}>
                Annuler la période…
              </AdminButton>
              <AdminButton variant="danger" onClick={supprimer} loading={envoi}>
                Supprimer la période {periode.numero}
              </AdminButton>
            </>
          ) : (
            <AdminButton type="submit" form="form-annuler-periode" variant="danger" disabled={!motif.trim()} loading={envoi}>
              Annuler la période {periode.numero}
            </AdminButton>
          )}
        </>
      }
    >
      {message && (
        <Banner tone="error">
          <strong>{message}</strong>
        </Banner>
      )}
      {etape === 'choix' ? (
        <>
          <p style={{ marginTop: 0 }}>
            <strong>Aucune heure encodée : suppression possible.</strong> Les {periode.nb_sessions} séances de la période {periode.numero} sont supprimées (aucun historique perdu). Les autres périodes ne sont pas touchées.
          </p>
          <p>
            <strong>Si des heures sont déjà encodées : annulation seulement.</strong> Les séances à venir sont annulées (motif obligatoire), les séances passées et leurs heures sont conservées.
          </p>
        </>
      ) : (
        <form id="form-annuler-periode" onSubmit={annuler} noValidate>
          {raison && (
            <Banner tone="warning">
              <strong>Suppression impossible :</strong> {raison}
            </Banner>
          )}
          <p style={{ marginTop: 0 }}>
            Les séances à venir sont annulées, les séances passées et leurs heures sont conservées. Les autres périodes ne sont pas touchées.
          </p>
          <AdminFormField label="Motif (obligatoire)" htmlFor="annuler-periode-motif" required>
            <AdminInput id="annuler-periode-motif" value={motif} onChange={(e) => setMotif(e.target.value)} />
          </AdminFormField>
        </form>
      )}
    </AdminModal>
  );
}

/** Historique des changements de cours d'une période : ancien → nouveau cours, auteur, date (4 états). */
export function HistoriqueCoursModal({ classe, periode, onClose }) {
  const histo = useHistoriqueCoursPeriode(classe.id, periode.id);
  return (
    <AdminModal
      isOpen
      size="md"
      title={`Changements de cours — période ${periode.numero}`}
      onClose={onClose}
      footer={
        <AdminButton variant="secondary" onClick={onClose}>
          Fermer
        </AdminButton>
      }
    >
      {histo.loading && <LoadingBlock message="Chargement de l'historique…" lignes={3} />}
      {histo.error && <ErrorBlock message="Impossible de charger l'historique. Réessayez." onRetry={histo.reload} />}
      {histo.data?.length === 0 && <p style={{ margin: 0 }}>Aucun changement de cours sur cette période.</p>}
      {histo.data?.length > 0 && (
        <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: ADMIN_SPACING.md }}>
          {histo.data.map((h) => (
            <li key={h.id} style={{ borderBottom: `1px solid ${ADMIN_COLORS.border}`, paddingBottom: ADMIN_SPACING.md }}>
              <strong>
                {h.ancien_cours?.titre || '—'} → {h.nouveau_cours?.titre || '—'}
              </strong>
              <div style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
                {h.par?.name ? `par ${h.par.name}` : 'par un utilisateur supprimé'} · {formatDate(h.date)}
              </div>
            </li>
          ))}
        </ul>
      )}
    </AdminModal>
  );
}
