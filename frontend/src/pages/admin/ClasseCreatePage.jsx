import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import { AdminFormField, AdminInput, AdminSelect } from '../../components/AdminFormField';
import Banner from '../../components/ui/Banner';
import LinkButton from '../../components/ui/LinkButton';
import { Section } from '../../components/ui/Card';
import { LoadingBlock, ErrorBlock } from '../../components/ui/DataStates';
import ClasseApercu from '../../components/classes/ClasseApercu';
import { anneeParDefaut, useAnneesScolaires } from '../../hooks/useAnneesScolaires';
import { useCours } from '../../hooks/useCours';
import { useCalendrierScolaire } from '../../hooks/useCalendrierScolaire';
import { apercuClasse, creerClasse } from '../../hooks/useClasses';
import { useHeuresDefrayables } from '../../hooks/useHeuresDefrayables';
import { formatDuree } from '../../utils/format';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { JOURS_SEMAINE, ajouterHeures, heuresEntre, formatHoraire, formatDate, formatDateLongue, libelleClasse, nomJour, parseDate } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';

const DELAI_APERCU_MS = 400;

/** Écran « Nouvelle classe » : formulaire + aperçu en direct des 14 dates (mock-up 02). */
export default function ClasseCreatePage() {
  const toast = useToast();
  const [params] = useSearchParams();
  const annees = useAnneesScolaires();
  const cours = useCours();

  const [form, setForm] = useState({
    cours_id: params.get('cours_id') || '',
    annee_scolaire_id: params.get('annee_scolaire_id') || '',
    periode_id: '',
    jour_semaine: '',
    lieu: '',
    heure_debut: '14:00',
    heure_fin: '15:30',
    date_premiere_session: '',
  });
  const [finManuelle, setFinManuelle] = useState(false); // tant que la fin n'est pas saisie à la main, elle suit début + durée de séance
  const [apercu, setApercu] = useState({ data: null, loading: false, erreurs: {}, message: null });
  const [envoi, setEnvoi] = useState(false);
  const [echec, setEchec] = useState(null); // { message, champs }
  const [creee, setCreee] = useState(null); // { classe, resume }

  const listeAnnees = useMemo(() => annees.data || [], [annees.data]);
  const anneeId = form.annee_scolaire_id || (anneeParDefaut(listeAnnees) ? String(anneeParDefaut(listeAnnees).id) : '');
  const annee = listeAnnees.find((a) => String(a.id) === anneeId);
  const periodeId = annee?.periodes.some((p) => String(p.id) === form.periode_id)
    ? form.periode_id
    : annee?.periodes[0]
      ? String(annee.periodes[0].id)
      : '';
  const periode = annee?.periodes.find((p) => String(p.id) === periodeId) || null;
  const calendrier = useCalendrierScolaire(anneeId);
  const calendrierVide = Boolean(anneeId) && !calendrier.loading && !calendrier.error && calendrier.data?.length === 0;

  // Durée de séance et heures défrayables applicables (défaut global ou valeur du cours) : le serveur décide.
  const anneeCivile = Number((form.date_premiere_session || annee?.date_debut || '').slice(0, 4)) || new Date().getFullYear();
  const defrayage = useHeuresDefrayables(form.cours_id, anneeCivile).data;
  const dureeSeance = defrayage?.duree_seance_defaut;

  useEffect(() => {
    if (!finManuelle && dureeSeance) {
      setForm((prev) => ({ ...prev, heure_fin: ajouterHeures(prev.heure_debut, dureeSeance) }));
    }
  }, [dureeSeance, finManuelle]);

  function maj(champ, valeur) {
    setForm((prev) => {
      const suivant = { ...prev, [champ]: valeur };
      if (champ === 'heure_debut' && !finManuelle && dureeSeance) suivant.heure_fin = ajouterHeures(valeur, dureeSeance);
      return suivant;
    });
    if (champ === 'heure_fin') setFinManuelle(true);
    setEchec(null);
  }

  const charge = useMemo(
    () => ({
      cours_id: Number(form.cours_id) || '',
      annee_scolaire_id: Number(anneeId) || '',
      periode_id: Number(periodeId) || '',
      jour_semaine: Number(form.jour_semaine) || '',
      heure_debut: form.heure_debut,
      heure_fin: form.heure_fin,
      lieu: form.lieu.trim() || null,
      date_premiere_session: form.date_premiere_session,
    }),
    [form, anneeId, periodeId],
  );
  const complet = Boolean(
    charge.cours_id && charge.annee_scolaire_id && charge.periode_id && charge.jour_semaine &&
      charge.heure_debut && charge.heure_fin && charge.date_premiere_session,
  );
  const cleCharge = JSON.stringify(charge);

  // Aperçu en direct : recalculé (avec un court délai) à chaque changement du formulaire.
  useEffect(() => {
    if (!complet || calendrierVide) {
      setApercu({ data: null, loading: false, erreurs: {}, message: null });
      return undefined;
    }
    let annule = false;
    setApercu((prev) => ({ ...prev, loading: true }));
    const minuteur = setTimeout(() => {
      apercuClasse(JSON.parse(cleCharge))
        .then((data) => !annule && setApercu({ data, loading: false, erreurs: {}, message: null }))
        .catch((err) => {
          if (annule) return;
          setApercu({ data: null, loading: false, erreurs: getFieldErrors(err), message: getErrorMessage(err) });
        });
    }, DELAI_APERCU_MS);
    return () => {
      annule = true;
      clearTimeout(minuteur);
    };
  }, [cleCharge, complet, calendrierVide]);

  const blocage = apercu.data?.blocage || null;
  const erreurs = { ...apercu.erreurs, ...(echec?.champs || {}) };
  const erreurDate = blocage ? blocage.message : erreurs.date_premiere_session;

  const dateChoisie = form.date_premiere_session ? parseDate(form.date_premiere_session) : null;
  const jourChoisi = Number(form.jour_semaine);
  const jourDeLaDate = dateChoisie ? (dateChoisie.getDay() === 0 ? 7 : dateChoisie.getDay()) : null;
  const dateRecalee = apercu.data?.recale ? apercu.data.date_premiere_session : null;

  async function soumettre(e) {
    e.preventDefault();
    if (!complet || blocage || calendrierVide) return;
    setEnvoi(true);
    setEchec(null);
    try {
      const classe = await creerClasse(charge);
      const seances = apercu.data?.seances || [];
      const sautees = apercu.data?.dates_sautees?.length;
      const resume = seances.length
        ? `${seances.length} sessions générées du ${formatDate(seances[0].date)} au ${formatDate(seances[seances.length - 1].date)}${
            sautees ? ` (${sautees} date${sautees > 1 ? 's' : ''} sautée${sautees > 1 ? 's' : ''})` : ''
          }`
        : `${classe.nb_sessions} sessions générées`;
      toast.success(`Classe « ${libelleClasse(classe)} » créée : ${resume}.`);
      setCreee({ classe, resume });
    } catch (err) {
      setEchec({ message: getErrorMessage(err, "La classe n'a pas pu être créée."), champs: getFieldErrors(err) });
    } finally {
      setEnvoi(false);
    }
  }

  function recommencer() {
    setCreee(null);
    setEchec(null);
    setForm((prev) => ({ ...prev, date_premiere_session: '', lieu: '' }));
  }

  const entete = (
    <AdminPageHeader
      icon="🏫"
      title="Nouvelle classe"
      breadcrumb={
        <>
          Scolarité › <Link to="/admin/classes">Classes</Link> › Nouvelle classe
        </>
      }
      description="Un seul formulaire : le cours, le créneau et la première date. Les 14 sessions sont générées à la validation."
    />
  );

  if (annees.loading || cours.loading) {
    return (
      <>
        {entete}
        <AdminPageContent>
          <LoadingBlock message="Chargement du formulaire…" />
        </AdminPageContent>
      </>
    );
  }
  if (annees.error || cours.error) {
    return (
      <>
        {entete}
        <AdminPageContent>
          <ErrorBlock
            message="Impossible de charger le formulaire. Vérifiez votre connexion puis réessayez."
            onRetry={() => {
              annees.reload();
              cours.reload();
            }}
          />
        </AdminPageContent>
      </>
    );
  }

  if (creee) {
    return (
      <>
        {entete}
        <AdminPageContent>
          <Banner
            tone="success"
            actions={
              <>
                <LinkButton to={`/admin/classes/${creee.classe.id}`} variant="primary" size="sm">
                  Ouvrir la classe
                </LinkButton>
                <AdminButton variant="secondary" size="sm" onClick={recommencer}>
                  Créer une autre classe
                </AdminButton>
              </>
            }
          >
            <strong>
              Classe « {libelleClasse(creee.classe)} » créée : {creee.resume}.
            </strong>
          </Banner>
        </AdminPageContent>
      </>
    );
  }

  const messageBlocage = 'Création bloquée : la 14e session dépasse la période';

  return (
    <>
      {entete}
      <AdminPageContent>
        {echec && (
          <Banner tone="error">
            <strong>La classe n'a pas pu être créée. Aucune session n'a été générée (l'opération a été annulée en entier).</strong>{' '}
            {echec.message} Corrigez le formulaire puis réessayez.
          </Banner>
        )}
        {calendrierVide && (
          <Banner
            tone="warning"
            actions={
              <LinkButton to="/admin/calendrier-scolaire" variant="primary" size="sm">
                Configurer le calendrier scolaire
              </LinkButton>
            }
          >
            <strong>Le calendrier scolaire {annee?.libelle} est vide.</strong> Sans lui, les sessions ne pourront pas
            sauter les vacances ni les jours fériés. Importez d'abord le calendrier FWB.
          </Banner>
        )}

        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 340px), 1fr))',
            gap: ADMIN_SPACING.xl,
            alignItems: 'start',
          }}
        >
          <form onSubmit={soumettre} noValidate aria-label="Nouvelle classe">
            <Section title="1 · Quelle classe ?" headingLevel={2}>
              <AdminFormField label="Cours du catalogue" htmlFor="classe-cours" required error={erreurs.cours_id}>
                <AdminSelect
                  id="classe-cours"
                  value={form.cours_id}
                  placeholder="Choisir un cours"
                  options={(cours.data || []).map((c) => ({ value: String(c.id), label: c.titre }))}
                  onChange={(e) => maj('cours_id', e.target.value)}
                  error={erreurs.cours_id}
                />
              </AdminFormField>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: ADMIN_SPACING.lg }}>
                <AdminFormField label="Année scolaire" htmlFor="classe-annee" required error={erreurs.annee_scolaire_id}>
                  <AdminSelect
                    id="classe-annee"
                    value={anneeId}
                    options={listeAnnees.map((a) => ({ value: String(a.id), label: a.libelle }))}
                    onChange={(e) => maj('annee_scolaire_id', e.target.value)}
                  />
                </AdminFormField>
                <AdminFormField label="Période" htmlFor="classe-periode" required error={erreurs.periode_id}>
                  <AdminSelect
                    id="classe-periode"
                    value={periodeId}
                    options={(annee?.periodes || []).map((p) => ({ value: String(p.id), label: `Période ${p.numero}` }))}
                    onChange={(e) => maj('periode_id', e.target.value)}
                    error={erreurs.periode_id}
                  />
                </AdminFormField>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: ADMIN_SPACING.lg }}>
                <AdminFormField label="Jour" htmlFor="classe-jour" required error={erreurs.jour_semaine}>
                  <AdminSelect
                    id="classe-jour"
                    value={form.jour_semaine}
                    placeholder="Choisir un jour"
                    options={JOURS_SEMAINE.map((j) => ({ value: String(j.value), label: j.label }))}
                    onChange={(e) => maj('jour_semaine', e.target.value)}
                    error={erreurs.jour_semaine}
                  />
                </AdminFormField>
                <AdminFormField label="Lieu" htmlFor="classe-lieu" error={erreurs.lieu}>
                  <AdminInput id="classe-lieu" value={form.lieu} onChange={(e) => maj('lieu', e.target.value)} error={erreurs.lieu} />
                </AdminFormField>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: ADMIN_SPACING.lg }}>
                <AdminFormField label="Début" htmlFor="classe-debut" required error={erreurs.heure_debut}>
                  <AdminInput id="classe-debut" type="time" value={form.heure_debut} onChange={(e) => maj('heure_debut', e.target.value)} error={erreurs.heure_debut} />
                </AdminFormField>
                <AdminFormField label="Fin" htmlFor="classe-fin" required error={erreurs.heure_fin}>
                  <AdminInput id="classe-fin" type="time" value={form.heure_fin} onChange={(e) => maj('heure_fin', e.target.value)} error={erreurs.heure_fin} />
                </AdminFormField>
              </div>
              {defrayage && (
                <Banner tone="info">
                  <strong>
                    Séance {formatDuree(heuresEntre(form.heure_debut, form.heure_fin))} · Défrayé {formatDuree(defrayage.valeur)}
                  </strong>{' '}
                  ({defrayage.source === 'cours' ? 'valeur définie sur le cours' : 'défaut global'}). Les professeurs sont défrayés pour ces heures
                  (cours + préparation) : 14 séances × {formatDuree(defrayage.valeur)} = {formatDuree(14 * defrayage.valeur)} défrayables.
                </Banner>
              )}
              <AdminFormField label="Date de la première session" htmlFor="classe-date" required error={erreurDate}>
                <AdminInput
                  id="classe-date"
                  type="date"
                  value={form.date_premiere_session}
                  onChange={(e) => maj('date_premiere_session', e.target.value)}
                  error={erreurDate}
                  aria-describedby="classe-date-aide"
                />
                <div id="classe-date-aide" style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginTop: ADMIN_SPACING.sm }} aria-live="polite">
                  {dateChoisie && jourChoisi && dateRecalee
                    ? `La date du ${formatDate(form.date_premiere_session)} est un ${nomJour(jourDeLaDate)}, pas un ${nomJour(jourChoisi)} : la première session sera recalée au ${formatDateLongue(dateRecalee)}. Choisissez un ${nomJour(jourChoisi)}, ou changez le jour de la classe.`
                    : dateChoisie && jourChoisi && jourDeLaDate === jourChoisi
                      ? `${formatDateLongue(form.date_premiere_session)} : correspond au jour choisi.`
                      : ''}
                </div>
              </AdminFormField>
            </Section>

            <div style={{ display: 'flex', gap: ADMIN_SPACING.md, justifyContent: 'flex-end', flexWrap: 'wrap', alignItems: 'center' }}>
              <LinkButton to="/admin/classes">Annuler</LinkButton>
              <AdminButton
                type="submit"
                disabled={!complet || Boolean(blocage) || calendrierVide || apercu.loading || !apercu.data}
                loading={envoi}
                aria-describedby={blocage ? 'classe-blocage' : undefined}
              >
                Créer la classe et générer 14 sessions
              </AdminButton>
            </div>
          </form>

          <div>
            {calendrierVide ? (
              <Section title="Aperçu indisponible">
                <p style={{ margin: 0 }}>
                  L'aperçu des 14 dates s'affichera dès que le calendrier scolaire sera renseigné. Le bouton de
                  création est désactivé d'ici là.
                </p>
              </Section>
            ) : (
              <>
                {apercu.message && !apercu.data && Object.keys(apercu.erreurs).length === 0 && (
                  <Banner tone="error">{apercu.message}</Banner>
                )}
                <ClasseApercu apercu={apercu.data} chargement={apercu.loading} periode={periode} />
                {blocage && (
                  <div
                    id="classe-blocage"
                    role="alert"
                    style={{
                      background: ADMIN_TONES.error.bg,
                      color: ADMIN_TONES.error.fg,
                      border: `1px solid ${ADMIN_TONES.error.border}`,
                      borderRadius: ADMIN_RADIUS.md,
                      padding: ADMIN_SPACING.lg,
                      marginBottom: ADMIN_SPACING.lg,
                    }}
                  >
                    <strong>{messageBlocage}</strong>
                    <ul style={{ margin: `${ADMIN_SPACING.sm} 0 0`, paddingLeft: '20px' }}>
                      <li>{blocage.message}.</li>
                      <li>Aucune session ne peut être créée ou déplacée après la fin de la période.</li>
                      <li>Pour tenir dans la période : avancez la date de première session, ou choisissez la période suivante.</li>
                    </ul>
                  </div>
                )}
                {apercu.data && !blocage && <Recapitulatif apercu={apercu.data} periode={periode} charge={charge} lieu={form.lieu} />}
              </>
            )}
          </div>
        </div>
      </AdminPageContent>
    </>
  );
}

function Recapitulatif({ apercu, periode, charge, lieu }) {
  const seances = apercu.seances;
  const premiere = seances[0]?.date;
  const derniere = seances[seances.length - 1]?.date;
  const sautees = apercu.dates_sautees.filter((d) => !derniere || d.date < derniere);
  return (
    <div
      role="region"
      aria-label="Récapitulatif avant génération"
      style={{
        background: ADMIN_TONES.primary.bg,
        color: ADMIN_TONES.primary.fg,
        border: `1px solid ${ADMIN_TONES.primary.border}`,
        borderRadius: ADMIN_RADIUS.md,
        padding: ADMIN_SPACING.lg,
      }}
    >
      <strong>Récapitulatif avant génération</strong>
      <ul style={{ margin: `${ADMIN_SPACING.sm} 0 0`, paddingLeft: '20px' }}>
        <li>
          <strong>{seances.length} sessions</strong> hebdomadaires, du {formatDate(premiere)} au {formatDate(derniere)} (
          {nomJour(charge.jour_semaine)} {formatHoraire(charge.heure_debut, charge.heure_fin)}
          {lieu.trim() ? `, ${lieu.trim()}` : ''}).
        </li>
        <li>
          {sautees.length === 0 ? (
            'Aucune date sautée.'
          ) : (
            <>
              <strong>
                {sautees.length} date{sautees.length > 1 ? 's' : ''} sautée{sautees.length > 1 ? 's' : ''}
              </strong>{' '}
              : {sautees.map((d) => formatDate(d.date).slice(0, 5)).join(', ')} (vacances, fériés ou fermetures du calendrier scolaire).
            </>
          )}
        </li>
        {periode && (
          <li>
            Période {periode.numero} : jusqu'au {formatDate(periode.date_fin)} — la séance {seances.length} ({formatDate(derniere)}) est dans la période. ✔
          </li>
        )}
      </ul>
    </div>
  );
}
