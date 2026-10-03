import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import AdminModal from '../../components/AdminModal';
import { AdminCheckbox, AdminInput } from '../../components/AdminFormField';
import Banner from '../../components/ui/Banner';
import LinkButton from '../../components/ui/LinkButton';
import StatutBadge from '../../components/ui/StatutBadge';
import PeriodeBadge from '../../components/classes/PeriodeBadge';
import FriseAnnee from '../../components/annees/FriseAnnee';
import { Section } from '../../components/ui/Card';
import { Table, Th, Td, Tr } from '../../components/ui/Table';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../../components/ui/DataStates';
import { apercuImpactAnnee, modifierAnnee, useAnneesScolaires } from '../../hooks/useAnneesScolaires';
import { useToast } from '../../hooks/useToast';
import { getErrorData, getErrorMessage, getFieldErrors, getStatus } from '../../api/errors';
import { evolutionPeriode, joursEntre, libelleRetour, retourValide, verifierPeriodes } from '../../utils/annees';
import { STATUTS_ANNEE } from '../../utils/statuts';
import { formatDate, formatDateHeure } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING, ADMIN_TONES } from '../../styles/AdminDesignSystem';

const DELAI_APERCU_MS = 400;
const pluriel = (n, mot, pluralMot) => `${n} ${n > 1 ? pluralMot || `${mot}s` : mot}`;

const formDepuis = (annee) => {
  const p = (n) => annee?.periodes?.find((x) => x.numero === n) || {};
  return { p1_debut: p(1).date_debut || '', p1_fin: p(1).date_fin || '', p2_debut: p(2).date_debut || '', p2_fin: p(2).date_fin || '' };
};
const periodesDepuis = (f) => [
  { numero: 1, date_debut: f.p1_debut, date_fin: f.p1_fin },
  { numero: 2, date_debut: f.p2_debut, date_fin: f.p2_fin },
];

/** Phrases d'impact (non bloquantes) à partir de la réponse de l'aperçu. */
function phrasesImpact(impact) {
  if (!impact) return [];
  const horsClasses = new Set((impact.par_classe || []).filter((c) => c.type === 'hors_periode').map((c) => c.classe_id)).size;
  const phrases = [];
  if (impact.seances_hors_periode_en_plus > 0) {
    phrases.push(`${pluriel(impact.seances_hors_periode_en_plus, 'séance')} ${horsClasses ? `de ${pluriel(horsClasses, 'classe')} ` : ''}passeront « hors période »`);
  }
  if (impact.classes_demarrant_avant_debut > 0) {
    phrases.push(`${pluriel(impact.classes_demarrant_avant_debut, 'classe')} ${impact.classes_demarrant_avant_debut > 1 ? 'ont' : 'a'} une période qui démarre avant le nouveau début`);
  }
  if (impact.alertes_p2_creees > 0) phrases.push(`${pluriel(impact.alertes_p2_creees, 'classe')} passeraient en alerte « P2 à planifier »`);
  if (impact.alertes_p2_supprimees > 0) phrases.push(`${pluriel(impact.alertes_p2_supprimees, 'alerte')} « P2 à planifier » disparaîtraient`);
  if (impact.calendrier_hors_annee > 0) phrases.push(`${pluriel(impact.calendrier_hors_annee, 'date')} du calendrier scolaire resteraient conservées mais hors année`);
  return phrases;
}

const aDesEffets = (impact) =>
  Boolean(impact) &&
  (impact.classes_touchees > 0 ||
    impact.seances_hors_periode_en_plus > 0 ||
    impact.classes_demarrant_avant_debut > 0 ||
    impact.alertes_p2_creees > 0 ||
    impact.alertes_p2_supprimees > 0);

/** Écran « Modifier les périodes » d'une année, avec aperçu d'impact avant enregistrement (mock-up CLS-03/03). */
export default function AnneePeriodesPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const toast = useToast();
  const [params] = useSearchParams();
  const retour = retourValide(params.get('retour'));
  const periodeMiseEnAvant = Number(params.get('periode')) || null;
  const annees = useAnneesScolaires();
  const annee = (annees.data || []).find((a) => String(a.id) === String(id)) || null;

  const [base, setBase] = useState(null); // année de référence (« Avant ») et version à renvoyer
  const [form, setForm] = useState(null);
  const [erreurs, setErreurs] = useState({});
  const [apercu, setApercu] = useState({ etat: 'idle', data: null }); // idle | calcul | erreur | ok
  const [essai, setEssai] = useState(0);
  const [confirmation, setConfirmation] = useState(false);
  const [coche, setCoche] = useState(false);
  const [envoi, setEnvoi] = useState(false);
  const [echec, setEchec] = useState(null); // message
  const [concurrence, setConcurrence] = useState(null); // { message, annee }
  const [verrou, setVerrou] = useState(null); // message annee_archivee
  const [succes, setSucces] = useState(null);
  const initialise = useRef(false);

  useEffect(() => {
    if (annee && !initialise.current) {
      initialise.current = true;
      setBase(annee);
      setForm(formDepuis(annee));
    }
  }, [annee]);

  const avant = useMemo(() => formDepuis(base), [base]);
  const modifie = Boolean(form) && Object.keys(avant).some((c) => avant[c] !== form[c]);
  const coherence = useMemo(() => (form ? verifierPeriodes(form) : null), [form]);
  const calculable = modifie && coherence?.complet && coherence.bloquants.length === 0;
  const cleDates = form ? JSON.stringify(form) : '';
  const lecteurSeul = Boolean(base) && (base.statut === 'archivee' || base.can?.update === false || Boolean(verrou));

  // Aperçu d'impact : recalculé (debounce) à chaque jeu de dates valide et différent de l'existant.
  useEffect(() => {
    if (!calculable || lecteurSeul) {
      setApercu({ etat: 'idle', data: null });
      return undefined;
    }
    let annule = false;
    setApercu((prev) => ({ ...prev, etat: 'calcul' }));
    const t = setTimeout(() => {
      apercuImpactAnnee(base.id, periodesDepuis(JSON.parse(cleDates)))
        .then((data) => !annule && setApercu({ etat: 'ok', data }))
        .catch(() => !annule && setApercu({ etat: 'erreur', data: null }));
    }, DELAI_APERCU_MS);
    return () => {
      annule = true;
      clearTimeout(t);
    };
  }, [calculable, cleDates, base, lecteurSeul, essai]);

  function maj(champ, valeur) {
    setForm((prev) => ({ ...prev, [champ]: valeur }));
    setErreurs((prev) => ({ ...prev, [champ]: undefined }));
    setEchec(null);
  }

  const impact = apercu.etat === 'ok' ? apercu.data : null;
  const bloquantsServeur = impact?.bloquants || [];
  const bloque = (coherence?.bloquants.length || 0) > 0 || bloquantsServeur.length > 0;
  const peutEnregistrer = modifie && !bloque && !lecteurSeul && apercu.etat === 'ok' && !envoi;
  const effets = aDesEffets(impact);
  const phrases = phrasesImpact(impact);

  const changements = useMemo(() => {
    if (!form) return [];
    return [1, 2].flatMap((n) =>
      ['debut', 'fin']
        .filter((b) => avant[`p${n}_${b}`] !== form[`p${n}_${b}`])
        .map((b) => {
          const a = avant[`p${n}_${b}`];
          const d = form[`p${n}_${b}`];
          const jours = joursEntre(a, d);
          return { numero: n, borne: b, avant: a, apres: d, jours };
        }),
    );
  }, [avant, form]);

  async function enregistrer() {
    if (!peutEnregistrer) return;
    setEnvoi(true);
    setEchec(null);
    setErreurs({});
    try {
      const reponse = await modifierAnnee(base.id, {
        date_debut: form.p1_debut,
        date_fin: form.p2_fin,
        periodes: periodesDepuis(form),
        version: base.updated_at,
      });
      const maj = reponse?.data ?? reponse;
      (reponse?.avertissements || maj?.avertissements || []).forEach((m) => toast.warning(m));
      const resume = `Dates de l'année ${base.libelle} enregistrées. P1 : ${formatDate(form.p1_debut)} → ${formatDate(form.p1_fin)} · P2 : ${formatDate(form.p2_debut)} → ${formatDate(form.p2_fin)}.`;
      const phraseSeances =
        impact?.seances_hors_periode_en_plus > 0
          ? ` ${pluriel(impact.seances_hors_periode_en_plus, 'séance')} ${impact.seances_hors_periode_en_plus > 1 ? 'sont' : 'est'} maintenant marquée${impact.seances_hors_periode_en_plus > 1 ? 's' : ''} « hors période ».`
          : '';
      setConfirmation(false);
      toast.success(`Dates de l'année ${base.libelle} enregistrées.`);
      annees.reload();
      if (retour) {
        const premier = changements.find((c) => c.numero === periodeMiseEnAvant) || changements[0];
        const message = premier
          ? `Dates mises à jour : la période ${premier.numero} ${premier.borne === 'fin' ? 'se termine' : 'commence'} maintenant le ${formatDate(premier.apres)}.`
          : `Dates de l'année ${base.libelle} mises à jour.`;
        navigate(retour, { state: { datesMisesAJour: message } });
        return;
      }
      setSucces({ resume, consequence: phraseSeances, classes: impact?.classes_touchees || 0 });
    } catch (err) {
      setConfirmation(false);
      const donnees = getErrorData(err);
      if (getStatus(err) === 409 && donnees.code === 'modification_concurrente') {
        setConcurrence({ message: donnees.message || getErrorMessage(err), annee: donnees.annee || null });
      } else if (getStatus(err) === 409 && donnees.code === 'annee_archivee') {
        setVerrou(donnees.message || 'Réactivez l\'année pour modifier ses dates.');
      } else {
        const champs = getFieldErrors(err);
        setErreurs({
          p1_debut: champs['periodes.0.date_debut'] || champs.date_debut,
          p1_fin: champs['periodes.0.date_fin'],
          p2_debut: champs['periodes.1.date_debut'],
          p2_fin: champs['periodes.1.date_fin'] || champs.date_fin,
        });
        setEchec(getErrorMessage(err, "Les dates n'ont pas pu être enregistrées."));
      }
    } finally {
      setEnvoi(false);
    }
  }

  function ouvrirConfirmation() {
    if (effets) {
      setCoche(false);
      setConfirmation(true);
    } else {
      enregistrer();
    }
  }

  function rechargerSesDates() {
    if (concurrence?.annee) {
      setBase(concurrence.annee);
      setApercu({ etat: 'idle', data: null });
    } else {
      annees.reload();
      initialise.current = false;
    }
    setConcurrence(null);
  }

  function abandonner() {
    if (concurrence?.annee) {
      setBase(concurrence.annee);
      setForm(formDepuis(concurrence.annee));
    } else {
      setForm(formDepuis(base));
    }
    setConcurrence(null);
    setErreurs({});
    setApercu({ etat: 'idle', data: null });
  }

  const libelleAnnee = base?.libelle || annee?.libelle || '';
  const entete = (
    <AdminPageHeader
      icon="🗓️"
      title={`Modifier les périodes${libelleAnnee ? ` · ${libelleAnnee}` : ''}`}
      breadcrumb={
        <>
          Scolarité › <Link to="/admin/annees-scolaires">Années scolaires</Link>
          {libelleAnnee ? ` › ${libelleAnnee}` : ''} › Modifier les périodes
        </>
      }
      description="Voyez ce que votre changement provoque sur les classes avant d'enregistrer. Rien n'est jamais supprimé ni déplacé."
    />
  );

  if (annees.loading && !base) {
    return (
      <>
        {entete}
        <AdminPageContent>
          <LoadingBlock message="Chargement de l'année scolaire…" />
        </AdminPageContent>
      </>
    );
  }
  if (annees.error) {
    return (
      <>
        {entete}
        <AdminPageContent>
          <ErrorBlock message="Impossible de charger l'année scolaire. Vérifiez votre connexion puis réessayez." onRetry={annees.reload} />
        </AdminPageContent>
      </>
    );
  }
  if (!annee && !base) {
    return (
      <>
        {entete}
        <AdminPageContent>
          <EmptyBlock icon="🗓️" title="Année scolaire introuvable" actions={<LinkButton to="/admin/annees-scolaires">Retour aux années scolaires</LinkButton>}>
            Cette année n'existe plus ou n'est pas accessible.
          </EmptyBlock>
        </AdminPageContent>
      </>
    );
  }
  if (!base || !form) return null;

  if (succes) {
    return (
      <>
        {entete}
        <AdminPageContent>
          <Banner
            tone="success"
            actions={
              <>
                {succes.classes > 0 && (
                  <LinkButton to={`/admin/classes?annee_scolaire_id=${base.id}`} variant="primary" size="sm">
                    Voir les {succes.classes} classes concernées
                  </LinkButton>
                )}
                <LinkButton to="/admin/annees-scolaires" size="sm">
                  Retour aux années scolaires
                </LinkButton>
              </>
            }
          >
            <strong>{succes.resume}</strong>
            {succes.consequence}
          </Banner>
        </AdminPageContent>
      </>
    );
  }

  const evolution = (n) => evolutionPeriode(
    base.periodes?.find((p) => p.numero === n),
    { date_debut: form[`p${n}_debut`], date_fin: form[`p${n}_fin`] },
  );
  const classesNommees = [...new Set((impact?.par_classe || []).map((c) => c.titre))];

  return (
    <>
      {entete}
      <AdminPageContent>
        {retour && (
          <Banner tone="info" actions={<LinkButton to={retour} size="sm">← Retour au formulaire</LinkButton>}>
            ↩ Vous modifiez les dates depuis <strong>{libelleRetour(retour)}</strong>. Votre saisie est conservée.
          </Banner>
        )}

        {base.statut === 'archivee' || verrou ? (
          <Banner tone="warning" actions={<LinkButton to="/admin/annees-scolaires" size="sm">Retour aux années scolaires</LinkButton>}>
            <strong>Année archivée : dates verrouillées.</strong> {verrou || "Réactivez l'année pour modifier ses dates."}
          </Banner>
        ) : base.can?.update === false ? (
          <Banner tone="warning">Vous n'avez pas le droit de modifier les dates de cette année.</Banner>
        ) : null}

        {concurrence && (
          <Banner
            tone="error"
            actions={
              <>
                <AdminButton size="sm" onClick={rechargerSesDates}>Recharger ses dates et reprendre ma saisie</AdminButton>
                <AdminButton size="sm" variant="secondary" onClick={abandonner}>Abandonner</AdminButton>
              </>
            }
          >
            <strong>{concurrence.message}</strong> Vos modifications n'ont pas été enregistrées.
          </Banner>
        )}
        {echec && (
          <Banner tone="error">
            <strong>Les dates n'ont pas pu être enregistrées.</strong> {echec} Votre saisie est conservée.
          </Banner>
        )}

        <Section
          title={
            <span style={{ display: 'inline-flex', gap: ADMIN_SPACING.sm, alignItems: 'center', flexWrap: 'wrap' }}>
              Dates des périodes <StatutBadge table={STATUTS_ANNEE} valeur={base.statut} />
            </span>
          }
          subtitle={`Libellé ${base.libelle}${base.updated_at ? ` · dernière modification${base.updated_by?.name ? ` par ${base.updated_by.name}` : ''}, ${formatDateHeure(base.updated_at)}` : ''}`}
          headingLevel={2}
        >
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: ADMIN_SPACING.lg, marginBottom: ADMIN_SPACING.lg }}>
            <FriseAnnee periodes={periodesDepuis(avant)} titre="Avant" attenuee />
            <FriseAnnee periodes={periodesDepuis(form)} titre="Après (vos dates)" />
          </div>

          <Table caption={`Périodes de l'année ${base.libelle} : dates actuelles et nouvelles dates`} minWidth="640px">
            <thead>
              <tr>
                <Th>Période</Th>
                <Th>Actuellement</Th>
                <Th>Du</Th>
                <Th>Au</Th>
                <Th>Évolution</Th>
              </tr>
            </thead>
            <tbody>
              {[1, 2].map((n) => {
                const ancienne = base.periodes?.find((p) => p.numero === n);
                const change = avant[`p${n}_debut`] !== form[`p${n}_debut`] || avant[`p${n}_fin`] !== form[`p${n}_fin`];
                return (
                  <Tr key={n} fond={change ? ADMIN_TONES.warning.bg : periodeMiseEnAvant === n ? ADMIN_COLORS.hoverBg : undefined}>
                    <Td>
                      <PeriodeBadge numero={n} /> Période {n}
                    </Td>
                    <Td style={{ color: ADMIN_COLORS.textSecondary, whiteSpace: 'nowrap' }}>
                      {ancienne ? `${formatDate(ancienne.date_debut)} → ${formatDate(ancienne.date_fin)}` : '—'}
                    </Td>
                    {['debut', 'fin'].map((b) => {
                      const champ = `p${n}_${b}`;
                      return (
                        <Td key={b}>
                          <label htmlFor={`periode-${champ}`} className="sr-only">
                            P{n} {b === 'debut' ? 'du' : 'au'}
                          </label>
                          <AdminInput
                            id={`periode-${champ}`}
                            type="date"
                            value={form[champ]}
                            disabled={lecteurSeul}
                            error={erreurs[champ]}
                            aria-describedby={erreurs[champ] ? `erreur-${champ}` : undefined}
                            onChange={(e) => maj(champ, e.target.value)}
                          />
                          {erreurs[champ] && (
                            <div id={`erreur-${champ}`} role="alert" style={{ fontSize: '12px', color: ADMIN_COLORS.error, marginTop: ADMIN_SPACING.xs }}>
                              {erreurs[champ]}
                            </div>
                          )}
                        </Td>
                      );
                    })}
                    <Td>{evolution(n) ? <strong>{evolution(n)}</strong> : '—'}</Td>
                  </Tr>
                );
              })}
            </tbody>
          </Table>
          <p style={{ margin: `${ADMIN_SPACING.md} 0 0`, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
            L'année va du début de la P1 à la fin de la P2
            {form.p1_debut && form.p2_fin ? ` : ${formatDate(form.p1_debut)} → ${formatDate(form.p2_fin)}` : ''}. Le trou entre les périodes (vacances) est normal.
          </p>
        </Section>

        <Section
          title="Impact sur l'existant"
          subtitle={`Calculé avec vos dates avant enregistrement · année ${base.libelle} · ${pluriel(base.classes_count ?? 0, 'classe')}, ${pluriel(base.sessions_count ?? 0, 'séance')}`}
          headingLevel={2}
        >
          <div aria-live="polite" aria-busy={apercu.etat === 'calcul'}>
            {lecteurSeul ? (
              <p style={{ margin: 0 }}>Les dates sont verrouillées : aucun calcul d'impact.</p>
            ) : !modifie ? (
              <p style={{ margin: 0 }}>Modifiez une date pour voir ce qui changerait pour vos classes.</p>
            ) : coherence.bloquants.length > 0 ? (
              <BandeauBloquant messages={coherence.bloquants} note="Aucun calcul d'impact tant que les dates sont incohérentes." />
            ) : apercu.etat === 'erreur' ? (
              <Banner
                tone="error"
                actions={<AdminButton size="sm" variant="secondary" onClick={() => setEssai((n) => n + 1)}>Réessayer</AdminButton>}
              >
                <strong>L'impact n'a pas pu être calculé.</strong> Par prudence, l'enregistrement est désactivé.
              </Banner>
            ) : !impact ? (
              <LoadingBlock message="Calcul de l'impact…" lignes={3} />
            ) : bloquantsServeur.length > 0 ? (
              <BandeauBloquant messages={bloquantsServeur} />
            ) : (
              <>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: ADMIN_SPACING.md, marginBottom: ADMIN_SPACING.lg }}>
                  <Compteur valeur={impact.classes_touchees} libelle="classes touchées" />
                  <Compteur valeur={impact.seances_hors_periode_en_plus} libelle="séances « hors période » en plus" />
                  <Compteur valeur={impact.classes_demarrant_avant_debut} libelle="démarrent avant la nouvelle date" />
                  <Compteur valeur={bloquantsServeur.length} libelle="blocages" />
                </div>
                {(impact.avertissements || []).map((m) => (
                  <Banner key={m} tone="warning">
                    <strong>Avertissement (non bloquant) :</strong> {m}
                  </Banner>
                ))}
                {effets ? (
                  <Banner tone="warning">
                    <strong>⚠ {phrases.join(' ; ')}.</strong> Ce n'est pas bloquant : les séances restent planifiées.
                  </Banner>
                ) : (
                  <Banner tone="success">
                    <strong>✓ Aucun effet négatif.</strong>{' '}
                    {impact.seances_redevenant_dans_periode > 0
                      ? `${pluriel(impact.seances_redevenant_dans_periode, 'séance')} ${impact.seances_redevenant_dans_periode > 1 ? 'redeviennent' : 'redevient'} dans la période.`
                      : 'Vos classes et séances ne sont pas touchées.'}
                  </Banner>
                )}
                {effets && impact.seances_redevenant_dans_periode > 0 && (
                  <p style={{ fontSize: '13px' }}>
                    {pluriel(impact.seances_redevenant_dans_periode, 'séance')} {impact.seances_redevenant_dans_periode > 1 ? 'redeviennent' : 'redevient'} aussi dans la période.
                  </p>
                )}
                {(impact.par_classe || []).length > 0 && <TableauParClasse lignes={impact.par_classe} />}
                <p style={{ margin: `${ADMIN_SPACING.md} 0 0`, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
                  Rappel : aucune séance n'est déplacée, annulée ou supprimée ; les heures encodées (timesheets) ne changent pas. « Hors
                  période » est un marqueur calculé : il disparaît si les dates sont rétablies.
                </p>
              </>
            )}
          </div>
        </Section>

        <div style={{ display: 'flex', gap: ADMIN_SPACING.md, justifyContent: 'flex-end', flexWrap: 'wrap', alignItems: 'center' }}>
          <LinkButton to={retour || '/admin/annees-scolaires'}>Annuler</LinkButton>
          <AdminButton onClick={ouvrirConfirmation} disabled={!peutEnregistrer} loading={envoi && !confirmation}>
            Enregistrer les dates
          </AdminButton>
        </div>
      </AdminPageContent>

      {confirmation && (
        <AdminModal
          isOpen
          size="md"
          title={`Confirmer la modification des périodes ${base.libelle}`}
          onClose={() => setConfirmation(false)}
          closeOnBackdrop={false}
          footer={
            <>
              <AdminButton variant="secondary" onClick={() => setConfirmation(false)}>
                Revenir à mes dates
              </AdminButton>
              <AdminButton onClick={enregistrer} disabled={!coche} loading={envoi}>
                Enregistrer les dates
              </AdminButton>
            </>
          }
        >
          <strong>Ce que vous changez</strong>
          <ul style={{ margin: `${ADMIN_SPACING.sm} 0 ${ADMIN_SPACING.lg}`, paddingLeft: '20px' }}>
            {changements.map((c) => (
              <li key={`${c.numero}${c.borne}`}>
                <PeriodeBadge numero={c.numero} /> {c.borne === 'debut' ? 'début' : 'fin'} : {formatDate(c.avant)} → <strong>{formatDate(c.apres)}</strong> (
                {pluriel(Math.abs(c.jours), 'jour')} {c.jours > 0 ? 'de plus' : 'de moins'})
              </li>
            ))}
            {form.p2_fin !== avant.p2_fin && <li>L'année se terminera le {formatDate(form.p2_fin)}.</li>}
          </ul>
          <strong>Conséquences sur l'existant</strong>
          <ul style={{ margin: `${ADMIN_SPACING.sm} 0 ${ADMIN_SPACING.lg}`, paddingLeft: '20px' }}>
            {phrases.map((p) => (
              <li key={p}>
                {p}
                {classesNommees.length > 0 && p === phrases[0] ? ` (${classesNommees.join(', ')})` : ''}.
              </li>
            ))}
            <li>Aucune séance supprimée, déplacée ni annulée ; heures encodées inchangées.</li>
            {impact?.seances_hors_periode_en_plus > 0 && <li>Rétablir les anciennes dates supprime ces marqueurs.</li>}
          </ul>
          <AdminCheckbox
            id="confirmer-impact"
            checked={coche}
            onChange={(e) => setCoche(e.target.checked)}
            label={`Je confirme : ${
              impact?.seances_hors_periode_en_plus > 0
                ? `${pluriel(impact.seances_hors_periode_en_plus, 'séance')} ${impact.seances_hors_periode_en_plus > 1 ? 'seront marquées' : 'sera marquée'} « hors période »`
                : phrases[0] || 'ces changements sont voulus'
            }.`}
          />
        </AdminModal>
      )}
    </>
  );
}

function Compteur({ valeur, libelle }) {
  return (
    <div style={{ border: `1px solid ${ADMIN_COLORS.border}`, borderRadius: ADMIN_RADIUS.md, padding: ADMIN_SPACING.md, textAlign: 'center' }}>
      <div style={{ fontSize: '24px', fontWeight: 700, color: ADMIN_COLORS.textPrimary }}>{valeur ?? 0}</div>
      <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>{libelle}</div>
    </div>
  );
}

function BandeauBloquant({ messages, note }) {
  return (
    <div
      role="alert"
      style={{
        background: ADMIN_TONES.error.bg,
        color: ADMIN_TONES.error.fg,
        border: `1px solid ${ADMIN_TONES.error.border}`,
        borderRadius: ADMIN_RADIUS.md,
        padding: ADMIN_SPACING.lg,
      }}
    >
      {messages.map((m) => (
        <div key={m}>
          <strong>✖ Impossible :</strong> {m}
        </div>
      ))}
      {note && <div style={{ marginTop: ADMIN_SPACING.sm }}>{note}</div>}
    </div>
  );
}

const TYPES_LIGNE = {
  hors_periode: (c) => `${c.nb_seances} hors période`,
  demarre_avant_debut: (c) => `${c.nb_seances} avant le début`,
  alerte_p2: () => 'Alerte « P2 à planifier »',
};

function TableauParClasse({ lignes }) {
  return (
    <Table caption="Classes touchées par le changement de dates" minWidth="640px">
      <thead>
        <tr>
          <Th>Classe</Th>
          <Th>Période</Th>
          <Th>Séances concernées</Th>
          <Th>Détail</Th>
          <Th srOnly>Lien</Th>
        </tr>
      </thead>
      <tbody>
        {lignes.map((c, i) => (
          <Tr key={`${c.classe_id}-${c.type}-${i}`}>
            <Td>
              <strong>{c.titre}</strong>
              {c.creneau && <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>{c.creneau}</div>}
            </Td>
            <Td>{c.periode_numero ? <PeriodeBadge numero={c.periode_numero} /> : '—'}</Td>
            <Td>{(TYPES_LIGNE[c.type] || (() => `${c.nb_seances ?? 0}`))(c)}</Td>
            <Td style={{ fontSize: '13px' }}>{(c.dates || []).map((d) => formatDate(d)).join(' · ') || '—'}</Td>
            <Td>
              <Link to={`/admin/classes/${c.classe_id}`}>
                Ouvrir la classe<span className="sr-only"> {c.titre}</span>
              </Link>
            </Td>
          </Tr>
        ))}
      </tbody>
    </Table>
  );
}
