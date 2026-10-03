import { useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import { AdminFormField, AdminInput, AdminSelect, AdminCheckbox } from '../../components/AdminFormField';
import Banner from '../../components/ui/Banner';
import LinkButton from '../../components/ui/LinkButton';
import { Section } from '../../components/ui/Card';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../../components/ui/DataStates';
import LienModifierDates from '../../components/annees/LienModifierDates';
import ClasseApercu from '../../components/classes/ClasseApercu';
import PeriodeBadge from '../../components/classes/PeriodeBadge';
import { anneeParDefaut, useAnneesScolaires } from '../../hooks/useAnneesScolaires';
import { useCours } from '../../hooks/useCours';
import { useCalendrierScolaire } from '../../hooks/useCalendrierScolaire';
import { apercuClasse, creerClasse } from '../../hooks/useClasses';
import { useHeuresDefrayables } from '../../hooks/useHeuresDefrayables';
import { formatDuree } from '../../utils/format';
import { useToast } from '../../hooks/useToast';
import { getErrorData, getErrorMessage, getFieldErrors } from '../../api/errors';
import { contexteHorsBornes, effacerBrouillon, lienNouvelleAnnee, lireBrouillon, sauverBrouillon } from '../../utils/annees';
import { JOURS_SEMAINE, ajouterHeures, heuresEntre, formatDate, formatDateLongue, jourDeClasseApres, libelleClasse, nomJour, parseDate } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';

const DELAI_APERCU_MS = 400;
const CLE_BROUILLON = 'classe-nouvelle';
const CHEMIN_RETOUR = '/admin/classes/nouvelle?restaurer=1';

/** Écran « Nouvelle classe » : créneau commun + une ou deux périodes (cours + date), aperçu en direct (mock-up CLS-02/01). */
export default function ClasseCreatePage() {
  const toast = useToast();
  const [params] = useSearchParams();
  const location = useLocation();
  const annees = useAnneesScolaires();
  const cours = useCours();
  // Saisie conservée pendant un détour par la gestion des années scolaires (restaurée une seule fois au retour).
  const [brouillon] = useState(() => (params.get('restaurer') ? lireBrouillon(CLE_BROUILLON) : null));
  const [datesMisesAJour] = useState(() => location.state?.datesMisesAJour || null);
  useEffect(() => {
    effacerBrouillon(CLE_BROUILLON);
  }, []);

  const [form, setForm] = useState(
    brouillon?.form || {
      annee_scolaire_id: params.get('annee_scolaire_id') || '',
      jour_semaine: '',
      lieu: '',
      heure_debut: '14:00',
      heure_fin: '15:30',
    },
  );
  const [ouvertes, setOuvertes] = useState(brouillon?.ouvertes || { 1: true, 2: false });
  const [per, setPer] = useState(
    brouillon?.per || {
      1: { cours_id: params.get('cours_id') || '', date: '' },
      2: { cours_id: '', date: '' },
    },
  );
  const [dateP2Manuelle, setDateP2Manuelle] = useState(brouillon?.dateP2Manuelle ?? false);
  const [finManuelle, setFinManuelle] = useState(brouillon?.finManuelle ?? false); // tant que la fin n'est pas saisie à la main, elle suit début + durée de séance
  const [apercu, setApercu] = useState({ data: null, loading: false, erreurs: {}, message: null, contexte: null });
  const [envoi, setEnvoi] = useState(false);
  const [echec, setEchec] = useState(null); // { message, champs }
  const [creee, setCreee] = useState(null); // { classe, resume }

  const listeAnnees = useMemo(() => (annees.data || []).filter((a) => a.statut !== 'archivee'), [annees.data]);
  const anneeId = form.annee_scolaire_id || (anneeParDefaut(listeAnnees) ? String(anneeParDefaut(listeAnnees).id) : '');
  const annee = listeAnnees.find((a) => String(a.id) === anneeId);
  const periodeAnnee = (numero) => annee?.periodes.find((p) => p.numero === numero) || null;
  const numerosOuverts = [1, 2].filter((n) => ouvertes[n]);
  const premierOuvert = numerosOuverts[0];
  const calendrier = useCalendrierScolaire(anneeId);
  const calendrierVide = Boolean(anneeId) && !calendrier.loading && !calendrier.error && calendrier.data?.length === 0;

  // Durée de séance et heures défrayables applicables (défaut global ou valeur du cours) : le serveur décide.
  const coursRef = per[premierOuvert]?.cours_id;
  const anneeCivile = Number((per[premierOuvert]?.date || annee?.date_debut || '').slice(0, 4)) || new Date().getFullYear();
  const defrayage = useHeuresDefrayables(coursRef, anneeCivile).data;
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

  function majPeriode(numero, champ, valeur) {
    setPer((prev) => ({ ...prev, [numero]: { ...prev[numero], [champ]: valeur } }));
    if (numero === 2 && champ === 'date') setDateP2Manuelle(true);
    setEchec(null);
  }

  function basculer(numero, ouverte) {
    const autres = numerosOuverts.filter((n) => n !== numero);
    if (!ouverte && autres.length === 0) return; // au moins une période
    setOuvertes((prev) => ({ ...prev, [numero]: ouverte }));
    if (numero === 2 && ouverte) setDateP2Manuelle(false);
    setEchec(null);
  }

  const charge = useMemo(
    () => ({
      annee_scolaire_id: Number(anneeId) || '',
      jour_semaine: Number(form.jour_semaine) || '',
      heure_debut: form.heure_debut,
      heure_fin: form.heure_fin,
      lieu: form.lieu.trim() || null,
      periodes: [1, 2]
        .filter((n) => ouvertes[n])
        .map((n) => ({
          periode_id: Number(annee?.periodes.find((p) => p.numero === n)?.id) || '',
          cours_id: Number(per[n].cours_id) || '',
          date_premiere_session: per[n].date,
        })),
    }),
    [form, anneeId, annee, ouvertes, per],
  );
  const complet = Boolean(
    charge.annee_scolaire_id && charge.jour_semaine && charge.heure_debut && charge.heure_fin && charge.periodes.length > 0 &&
      charge.periodes.every((p) => p.periode_id && p.cours_id && p.date_premiere_session),
  );
  const cleCharge = JSON.stringify(charge);

  // Aperçu en direct : recalculé (avec un court délai) à chaque changement du formulaire.
  useEffect(() => {
    if (!complet || calendrierVide) {
      setApercu({ data: null, loading: false, erreurs: {}, message: null, contexte: null });
      return undefined;
    }
    let annule = false;
    setApercu((prev) => ({ ...prev, loading: true }));
    const minuteur = setTimeout(() => {
      apercuClasse(JSON.parse(cleCharge))
        .then((data) => !annule && setApercu({ data, loading: false, erreurs: {}, message: null, contexte: null }))
        .catch((err) => {
          if (annule) return;
          setApercu({ data: null, loading: false, erreurs: getFieldErrors(err), message: getErrorMessage(err), contexte: contexteHorsBornes(getErrorData(err)) });
        });
    }, DELAI_APERCU_MS);
    return () => {
      annule = true;
      clearTimeout(minuteur);
    };
  }, [cleCharge, complet, calendrierVide]);

  // Date de la P2 pré-proposée : premier jour de classe après la dernière séance de la P1 (dans la période 2).
  const derniereP1 = apercu.data?.periodes?.find((p) => p.numero === 1)?.seances?.at(-1)?.date;
  const debutP2 = periodeAnnee(2)?.date_debut;
  useEffect(() => {
    if (!ouvertes[2] || !ouvertes[1] || dateP2Manuelle || !derniereP1 || !form.jour_semaine) return;
    const proposee = jourDeClasseApres(derniereP1, debutP2, Number(form.jour_semaine));
    if (proposee) setPer((prev) => (prev[2].date === proposee ? prev : { ...prev, 2: { ...prev[2], date: proposee } }));
  }, [ouvertes, dateP2Manuelle, derniereP1, debutP2, form.jour_semaine]);

  const planApercu = apercu.data?.periodes || [];
  const blocages = planApercu.filter((p) => p.blocage).map((p) => p.blocage);
  const blocage = blocages[0] || null;
  const nbSeances = planApercu.reduce((n, p) => n + p.seances.length, 0);
  const nbHors = planApercu.reduce((n, p) => n + p.seances.filter((s) => s.hors_periode).length, 0);
  const avertissements = planApercu.flatMap((p) => p.avertissements || []);
  const erreurs = { ...apercu.erreurs, ...(echec?.champs || {}) };
  const contexteBornes = apercu.contexte || echec?.contexte || null;
  const sauverSaisie = () => sauverBrouillon(CLE_BROUILLON, { form, ouvertes, per, dateP2Manuelle, finManuelle });
  const erreurPeriode = (numero, champ) => {
    const idx = charge.periodes.findIndex((_, i) => numerosOuverts[i] === numero);
    return erreurs[`periodes.${idx}.${champ}`] || (blocage?.periode_numero === numero && champ === 'date_premiere_session' ? blocage.message : undefined);
  };

  async function soumettre(e) {
    e.preventDefault();
    if (!complet || blocage || calendrierVide) return;
    setEnvoi(true);
    setEchec(null);
    try {
      const classe = await creerClasse(charge);
      const resume = planApercu
        .map((p) => {
          const sautees = p.dates_sautees?.length || 0;
          const debut = p.seances[0]?.date;
          const fin = p.seances[p.seances.length - 1]?.date;
          return `P${p.numero} : ${p.seances.length} séances du ${formatDate(debut)} au ${formatDate(fin)}${
            sautees ? ` (${sautees} date${sautees > 1 ? 's' : ''} sautée${sautees > 1 ? 's' : ''})` : ''
          }`;
        })
        .join(' · ') || `${classe.nb_sessions} séances générées`;
      toast.success(`Classe « ${libelleClasse(classe)} » créée. ${resume}.`);
      setCreee({ classe, resume });
    } catch (err) {
      setEchec({ message: getErrorMessage(err, "La classe n'a pas pu être créée."), champs: getFieldErrors(err), contexte: contexteHorsBornes(getErrorData(err)) });
    } finally {
      setEnvoi(false);
    }
  }

  function recommencer() {
    setCreee(null);
    setEchec(null);
    setDateP2Manuelle(false);
    setPer((prev) => ({ 1: { ...prev[1], date: '' }, 2: { ...prev[2], date: '' } }));
    setForm((prev) => ({ ...prev, lieu: '' }));
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
      description="Un seul formulaire : le groupe et le créneau, puis une ou deux périodes (cours + date de démarrage). Les 14 séances de chaque période sont générées à la validation."
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
              Classe « {libelleClasse(creee.classe)} » créée. {creee.resume}.
            </strong>
          </Banner>
        </AdminPageContent>
      </>
    );
  }

  if (listeAnnees.length === 0) {
    return (
      <>
        {entete}
        <AdminPageContent>
          <EmptyBlock
            icon="🗓️"
            title="Aucune année scolaire disponible"
            actions={
              <LinkButton to={lienNouvelleAnnee({ retour: CHEMIN_RETOUR })} variant="primary">
                Créer une année scolaire
              </LinkButton>
            }
          >
            Une classe appartient à une année scolaire. Créez l'année (avec ses deux périodes) puis revenez ici : votre saisie est conservée.
          </EmptyBlock>
        </AdminPageContent>
      </>
    );
  }

  const libelleCreation = !complet
    ? 'Créer la classe'
    : nbHors > 0
      ? `Créer quand même (${nbSeances} séances, ${nbHors} hors période)`
      : `Créer la classe (${nbSeances || numerosOuverts.length * 14} séances)`;
  const coursTitres = Object.fromEntries(
    numerosOuverts.map((n) => [n, (cours.data || []).find((c) => String(c.id) === String(per[n].cours_id))?.titre]),
  );
  const optionsCours = (cours.data || []).map((c) => ({ value: String(c.id), label: c.titre }));
  const jourChoisi = Number(form.jour_semaine);

  return (
    <>
      {entete}
      <AdminPageContent>
        {datesMisesAJour && (
          <Banner tone="success">
            <strong>{datesMisesAJour}</strong>
            {brouillon ? ' Votre saisie a été conservée : vérifiez la date de démarrage puis créez la classe.' : ''}
          </Banner>
        )}
        {echec && (
          <Banner tone="error">
            <strong>La classe n'a pas pu être créée. Aucune séance n'a été générée (l'opération a été annulée en entier).</strong>{' '}
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
            <strong>Le calendrier scolaire {annee?.libelle} est vide.</strong> Sans lui, les séances ne pourront pas
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
            <Section title="1 · Le groupe et le créneau" subtitle="Valables pour toutes les périodes" headingLevel={2}>
              <AdminFormField label="Année scolaire" htmlFor="classe-annee" required error={erreurs.annee_scolaire_id}>
                <AdminSelect
                  id="classe-annee"
                  value={anneeId}
                  options={listeAnnees.map((a) => ({ value: String(a.id), label: a.libelle }))}
                  onChange={(e) => maj('annee_scolaire_id', e.target.value)}
                />
              </AdminFormField>
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
                  (cours + préparation) : 14 séances × {formatDuree(defrayage.valeur)} = {formatDuree(14 * defrayage.valeur)} défrayables par période.
                </Banner>
              )}
            </Section>

            <Section title="2 · Périodes à ouvrir maintenant" headingLevel={2}>
              <fieldset style={{ border: 0, padding: 0, margin: `0 0 ${ADMIN_SPACING.lg}` }}>
                <legend style={{ fontSize: '12px', fontWeight: 600, textTransform: 'uppercase', marginBottom: ADMIN_SPACING.sm }}>
                  Périodes à créer
                </legend>
                <div style={{ display: 'flex', gap: ADMIN_SPACING.xl, flexWrap: 'wrap' }}>
                  {[1, 2].map((n) => (
                    <AdminCheckbox
                      key={n}
                      id={`periode-ouverte-${n}`}
                      label={n === 2 ? 'Ajouter la période 2 maintenant' : 'Période 1'}
                      checked={ouvertes[n]}
                      onChange={(e) => basculer(n, e.target.checked)}
                      disabled={ouvertes[n] && numerosOuverts.length === 1}
                    />
                  ))}
                </div>
                <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginTop: ADMIN_SPACING.sm }}>
                  Au moins une période. L'autre pourra être ajoutée plus tard depuis la fiche de la classe (utile pour une classe qui démarre en période 2).
                </div>
              </fieldset>

              {[1, 2].map((n) => {
                const pa = periodeAnnee(n);
                const planN = planApercu.find((p) => p.numero === n);
                const dateChoisie = per[n].date ? parseDate(per[n].date) : null;
                const jourDeLaDate = dateChoisie ? (dateChoisie.getDay() === 0 ? 7 : dateChoisie.getDay()) : null;
                if (!ouvertes[n]) {
                  return (
                    <p key={n} style={{ margin: `0 0 ${ADMIN_SPACING.lg}`, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
                      <PeriodeBadge numero={n} /> La période {n} n'est pas ouverte : vous pourrez l'ajouter plus tard depuis la fiche de la classe.
                    </p>
                  );
                }
                const erreurDate = erreurPeriode(n, 'date_premiere_session');
                return (
                  <fieldset
                    key={n}
                    style={{
                      border: `1px solid ${ADMIN_COLORS.border}`,
                      borderRadius: ADMIN_RADIUS.md,
                      padding: ADMIN_SPACING.lg,
                      margin: `0 0 ${ADMIN_SPACING.lg}`,
                    }}
                  >
                    <legend style={{ padding: `0 ${ADMIN_SPACING.sm}` }}>
                      <PeriodeBadge numero={n} /> <strong>Période {n}</strong> · 14 séances
                    </legend>
                    {pa && (
                      <p style={{ margin: `0 0 ${ADMIN_SPACING.lg}`, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
                        Bornes de la période : {formatDate(pa.date_debut)} → {formatDate(pa.date_fin)}
                        {annee?.can?.update && (
                          <>
                            {' · '}
                            <LienModifierDates annee={annee} numero={n} retour={CHEMIN_RETOUR} avantNavigation={sauverSaisie} />
                          </>
                        )}
                      </p>
                    )}
                    <AdminFormField label={`Cours du catalogue (P${n})`} htmlFor={`classe-cours-${n}`} required error={erreurPeriode(n, 'cours_id')}>
                      <AdminSelect
                        id={`classe-cours-${n}`}
                        value={per[n].cours_id}
                        placeholder="Choisir un cours"
                        options={optionsCours}
                        onChange={(e) => majPeriode(n, 'cours_id', e.target.value)}
                        error={erreurPeriode(n, 'cours_id')}
                      />
                      {n === 2 && (
                        <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginTop: ADMIN_SPACING.sm }}>
                          Peut être identique au cours de la période 1 (les liens par séance sont alors partagés).
                        </div>
                      )}
                    </AdminFormField>
                    <AdminFormField label={`Date de démarrage de la période ${n}`} htmlFor={`classe-date-${n}`} required error={erreurDate}>
                      <AdminInput
                        id={`classe-date-${n}`}
                        type="date"
                        value={per[n].date}
                        min={pa?.date_debut}
                        max={pa?.date_fin}
                        onChange={(e) => majPeriode(n, 'date', e.target.value)}
                        error={erreurDate}
                        aria-describedby={`classe-date-aide-${n}`}
                      />
                      <div id={`classe-date-aide-${n}`} style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginTop: ADMIN_SPACING.sm }} aria-live="polite">
                        {dateChoisie && jourChoisi && planApercu.length && planN?.recale
                          ? `La date du ${formatDate(per[n].date)} est un ${nomJour(jourDeLaDate)}, pas un ${nomJour(jourChoisi)} : la première séance sera recalée au ${formatDateLongue(planN.date_premiere_session)}. Choisissez un ${nomJour(jourChoisi)}, ou changez le jour de la classe.`
                          : dateChoisie && jourChoisi && jourDeLaDate === jourChoisi
                            ? `${formatDateLongue(per[n].date)} : correspond au jour choisi.`
                            : ''}
                        {n === 2 && ouvertes[1] && !dateP2Manuelle && per[2].date ? ' Proposée : 1er jour de classe après la dernière séance de la P1.' : ''}
                      </div>
                      {contexteBornes && contexteBornes.numero === n && annee?.can?.update && (
                        <div style={{ fontSize: '13px', marginTop: ADMIN_SPACING.sm }}>
                          <LienModifierDates annee={annee} numero={n} retour={CHEMIN_RETOUR} avantNavigation={sauverSaisie}>
                            Modifier les dates de la période {n} ({contexteBornes.annee_libelle || annee.libelle})
                          </LienModifierDates>{' '}
                          · ou choisissez une date comprise entre {formatDate(contexteBornes.debut)} et {formatDate(contexteBornes.fin)}.
                        </div>
                      )}
                    </AdminFormField>
                  </fieldset>
                );
              })}
            </Section>

            <div style={{ display: 'flex', gap: ADMIN_SPACING.md, justifyContent: 'flex-end', flexWrap: 'wrap', alignItems: 'center' }}>
              <LinkButton to="/admin/classes">Annuler</LinkButton>
              <AdminButton
                type="submit"
                variant={nbHors > 0 ? 'secondary' : 'primary'}
                disabled={!complet || Boolean(blocage) || calendrierVide || apercu.loading || !apercu.data}
                loading={envoi}
                aria-describedby={blocage ? 'classe-blocage' : undefined}
              >
                {libelleCreation}
              </AdminButton>
            </div>
          </form>

          <div>
            {calendrierVide ? (
              <Section title="Aperçu indisponible">
                <p style={{ margin: 0 }}>
                  L'aperçu des dates s'affichera dès que le calendrier scolaire sera renseigné. Le bouton de
                  création est désactivé d'ici là.
                </p>
              </Section>
            ) : (
              <>
                {apercu.message && !apercu.data && Object.keys(apercu.erreurs).length === 0 && (
                  <Banner tone="error">{apercu.message}</Banner>
                )}
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
                    <strong>Création impossible : les deux périodes se chevauchent</strong>
                    <p style={{ margin: `${ADMIN_SPACING.sm} 0 0` }}>{blocage.message}</p>
                    <p style={{ margin: `${ADMIN_SPACING.sm} 0 0` }}>
                      Règle : la 1re séance de la P2 doit être après la dernière séance de la P1. Corrigez l'une des deux dates.
                    </p>
                  </div>
                )}
                {nbHors > 0 && !blocage && avertissements.length === 0 && (
                  <Banner tone="warning">
                    <strong>Avertissement (non bloquant) :</strong> {nbHors} séance{nbHors > 1 ? 's' : ''} tomberai{nbHors > 1 ? 'ent' : 't'} après la fin de sa période : elle{nbHors > 1 ? 's seront créées' : ' sera créée'} avec le badge « hors période ».
                  </Banner>
                )}
                <ClasseApercu apercu={apercu.data} chargement={apercu.loading} cours={coursTitres} />
                {numerosOuverts.length === 1 && apercu.data && (
                  <p style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
                    Seule la période {numerosOuverts[0]} sera créée (14 séances). La période {numerosOuverts[0] === 1 ? 2 : 1} pourra être ajoutée plus tard.
                  </p>
                )}
              </>
            )}
          </div>
        </div>
      </AdminPageContent>
    </>
  );
}
