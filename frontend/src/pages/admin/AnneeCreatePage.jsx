import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import { AdminFormField, AdminInput, AdminSelect, AdminCheckbox } from '../../components/AdminFormField';
import Banner from '../../components/ui/Banner';
import LinkButton from '../../components/ui/LinkButton';
import { Section } from '../../components/ui/Card';
import { LoadingBlock } from '../../components/ui/DataStates';
import PeriodeBadge from '../../components/classes/PeriodeBadge';
import FriseAnnee from '../../components/annees/FriseAnnee';
import { creerAnneeScolaire, importerCalendrierFwb, proposerAnnee, useAnneesScolaires } from '../../hooks/useAnneesScolaires';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { lienModifierPeriodes, libelleRetour, retourValide, verifierPeriodes } from '../../utils/annees';
import { formatDate } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';

const FORM_VIDE = { libelle: '', p1_debut: '', p1_fin: '', p2_debut: '', p2_fin: '' };
const CHAMPS_DATES = ['p1_debut', 'p1_fin', 'p2_debut', 'p2_fin'];
const LIBELLE_VALIDE = /^\d{4}-\d{4}$/;

function depuisProposition(prop) {
  const p = (n) => prop.periodes?.find((x) => x.numero === n) || {};
  return { p1_debut: p(1).date_debut || '', p1_fin: p(1).date_fin || '', p2_debut: p(2).date_debut || '', p2_fin: p(2).date_fin || '' };
}

/** Écran « Nouvelle année scolaire » : proposition de dates, frise, cohérence en direct (mock-up CLS-03/02). */
export default function AnneeCreatePage() {
  const toast = useToast();
  const [params] = useSearchParams();
  const retour = retourValide(params.get('retour'));
  const annees = useAnneesScolaires();
  const [form, setForm] = useState(FORM_VIDE);
  const [statut, setStatut] = useState('active');
  const [importerFwb, setImporterFwb] = useState(true);
  const [touchees, setTouchees] = useState({}); // dates saisies à la main : jamais réécrasées par une proposition
  const [proposition, setProposition] = useState({ chargement: true, erreur: false, source: null, message: null });
  const [erreurs, setErreurs] = useState({});
  const [echec, setEchec] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const [creee, setCreee] = useState(null); // { annee, importMessage, importErreur }
  const touchesRef = useRef(touchees);
  touchesRef.current = touchees;
  const libelleRef = useRef('');

  // Proposition initiale (libellé = année suivante de la dernière) puis, à chaque libellé valide saisi, nouvelle proposition.
  function charger(libelle, { toutEcraser }) {
    let annule = false;
    setProposition((prev) => ({ ...prev, chargement: true, erreur: false }));
    proposerAnnee(libelle)
      .then((prop) => {
        if (annule) return;
        const dates = depuisProposition(prop);
        setForm((prev) => {
          const suivant = { ...prev };
          if (!libelle) {
            suivant.libelle = prev.libelle && !toutEcraser ? prev.libelle : prop.libelle || prev.libelle;
            libelleRef.current = suivant.libelle.trim();
          }
          CHAMPS_DATES.forEach((c) => {
            if (toutEcraser || !touchesRef.current[c]) suivant[c] = dates[c];
          });
          return suivant;
        });
        if (toutEcraser) setTouchees({});
        setProposition({ chargement: false, erreur: false, source: prop.source, message: prop.message });
      })
      .catch(() => !annule && setProposition({ chargement: false, erreur: true, source: null, message: null }));
    return () => {
      annule = true;
    };
  }

  useEffect(() => charger(undefined, { toutEcraser: false }), []); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    const libelle = form.libelle.trim();
    if (!LIBELLE_VALIDE.test(libelle) || libelle === libelleRef.current) return undefined;
    let annule = () => {};
    const t = setTimeout(() => {
      libelleRef.current = libelle;
      annule = charger(libelle, { toutEcraser: false });
    }, 400);
    return () => {
      clearTimeout(t);
      annule();
    };
  }, [form.libelle]); // eslint-disable-line react-hooks/exhaustive-deps

  function maj(champ, valeur) {
    setForm((prev) => ({ ...prev, [champ]: valeur }));
    if (CHAMPS_DATES.includes(champ)) setTouchees((prev) => ({ ...prev, [champ]: true }));
    setErreurs((prev) => ({ ...prev, [champ]: undefined }));
    setEchec(null);
  }

  const coherence = useMemo(() => verifierPeriodes(form), [form]);
  const autres = useMemo(() => annees.data || [], [annees.data]);
  const chevauchee = useMemo(() => {
    if (!form.p1_debut || !form.p2_fin) return null;
    return autres.find((a) => a.date_debut <= form.p2_fin && form.p1_debut <= a.date_fin) || null;
  }, [autres, form.p1_debut, form.p2_fin]);
  const libelleExiste = autres.some((a) => a.libelle === form.libelle.trim());
  const libelle = form.libelle.trim();
  const complet = Boolean(libelle && coherence.complet);
  const bloque = coherence.bloquants.length > 0 || Boolean(chevauchee) || libelleExiste;

  async function soumettre(e) {
    e.preventDefault();
    if (!complet || bloque) return;
    setEnvoi(true);
    setEchec(null);
    setErreurs({});
    try {
      const annee = await creerAnneeScolaire({
        libelle,
        date_debut: form.p1_debut,
        date_fin: form.p2_fin,
        statut,
        periodes: [
          { numero: 1, date_debut: form.p1_debut, date_fin: form.p1_fin },
          { numero: 2, date_debut: form.p2_debut, date_fin: form.p2_fin },
        ],
      });
      let importMessage = null;
      let importErreur = null;
      if (importerFwb && (annee.can?.import_fwb ?? true)) {
        try {
          const resultat = await importerCalendrierFwb(annee.id);
          importMessage = `Calendrier FWB importé : ${resultat.creees ?? 0} date${(resultat.creees ?? 0) > 1 ? 's' : ''}.`;
        } catch (errImport) {
          importErreur = `L'import du calendrier FWB a échoué : ${getErrorMessage(errImport)} Vous pouvez le relancer depuis la liste des années.`;
        }
      }
      toast.success(`Année ${annee.libelle} créée.`);
      setCreee({ annee, importMessage, importErreur });
    } catch (err) {
      const champs = getFieldErrors(err);
      setErreurs({
        libelle: champs.libelle,
        p1_debut: champs['periodes.0.date_debut'] || champs.date_debut,
        p1_fin: champs['periodes.0.date_fin'],
        p2_debut: champs['periodes.1.date_debut'],
        p2_fin: champs['periodes.1.date_fin'] || champs.date_fin,
      });
      setEchec(getErrorMessage(err, "L'année n'a pas pu être créée."));
    } finally {
      setEnvoi(false);
    }
  }

  const entete = (
    <AdminPageHeader
      icon="🗓️"
      title="Nouvelle année scolaire"
      breadcrumb={
        <>
          Scolarité › <Link to="/admin/annees-scolaires">Années scolaires</Link> › Nouvelle année
        </>
      }
      description="Deux périodes à dates pré-remplies : vérifiez, ajustez, créez."
    />
  );

  if (creee) {
    const { annee } = creee;
    const p = (n) => annee.periodes?.find((x) => x.numero === n);
    return (
      <>
        {entete}
        <AdminPageContent>
          <Banner tone="success">
            <strong>Année {annee.libelle} créée.</strong>{' '}
            {p(1) && p(2) && `P1 : ${formatDate(p(1).date_debut)} → ${formatDate(p(1).date_fin)} · P2 : ${formatDate(p(2).date_debut)} → ${formatDate(p(2).date_fin)}.`}{' '}
            {creee.importMessage}
          </Banner>
          {creee.importErreur && <Banner tone="warning">{creee.importErreur}</Banner>}
          <Section title="Et maintenant ?" headingLevel={2}>
            <div style={{ display: 'flex', gap: ADMIN_SPACING.md, flexWrap: 'wrap' }}>
              {retour && (
                <LinkButton to={retour} variant="primary">
                  ← Retour au formulaire ({libelleRetour(retour)})
                </LinkButton>
              )}
              <LinkButton to={`/admin/classes/nouvelle?annee_scolaire_id=${annee.id}`} variant={retour ? 'secondary' : 'primary'}>
                Créer une classe sur {annee.libelle}
              </LinkButton>
              <LinkButton to={`/admin/calendrier-scolaire?annee_scolaire_id=${annee.id}`}>Voir le calendrier scolaire {annee.libelle}</LinkButton>
              <LinkButton to="/admin/annees-scolaires">Retour à la liste des années</LinkButton>
            </div>
          </Section>
        </AdminPageContent>
      </>
    );
  }

  if (annees.loading || (proposition.chargement && !form.libelle)) {
    return (
      <>
        {entete}
        <AdminPageContent>
          <LoadingBlock message="Calcul des dates proposées…" lignes={4} />
        </AdminPageContent>
      </>
    );
  }

  const periodes = [
    { numero: 1, date_debut: form.p1_debut, date_fin: form.p1_fin },
    { numero: 2, date_debut: form.p2_debut, date_fin: form.p2_fin },
  ];
  const messagesBloquants = [
    ...coherence.bloquants,
    ...(chevauchee
      ? [`Cette année chevauche ${chevauchee.libelle} (qui se termine le ${formatDate(chevauchee.date_fin)}). Ajustez le début de la période 1 ou modifiez la fin de ${chevauchee.libelle}.`]
      : []),
    ...(libelleExiste ? [`Une année ${libelle} existe déjà.`] : []),
  ];
  const ton = messagesBloquants.length ? 'error' : coherence.avertissements.length ? 'warning' : 'success';

  return (
    <>
      {entete}
      <AdminPageContent>
        {retour && (
          <Banner tone="info">
            Vous créez l'année depuis <strong>{libelleRetour(retour)}</strong>. Votre saisie est conservée.{' '}
            <Link to={retour}>← Retour au formulaire</Link>
          </Banner>
        )}
        {proposition.erreur && (
          <Banner tone="warning">
            Les dates n'ont pas pu être proposées : saisissez-les à la main (la période 2 doit commencer après la fin de la période 1).
          </Banner>
        )}
        {!proposition.erreur && proposition.source && (
          <Banner
            tone={proposition.source === 'fwb' ? 'success' : 'info'}
            actions={
              <AdminButton
                size="sm"
                variant="secondary"
                onClick={() => {
                  setErreurs({});
                  charger(LIBELLE_VALIDE.test(libelle) ? libelle : undefined, { toutEcraser: true });
                }}
              >
                ↺ Rétablir la proposition
              </AdminButton>
            }
          >
            {proposition.source === 'fwb' ? (
              <>
                <strong>Dates proposées d'après le calendrier FWB {libelle}</strong> : P1 de la rentrée au vendredi avant les
                vacances de Carnaval, P2 de la reprise à la fin de l'année. Ajustez-les si besoin.
              </>
            ) : (
              <>
                <strong>{proposition.message || 'Proposition par défaut : calendrier FWB non importé.'}</strong> Ajustez les dates si besoin.
              </>
            )}
          </Banner>
        )}
        {echec && (
          <Banner tone="error">
            <strong>L'année n'a pas pu être créée.</strong> {echec} Votre saisie est conservée : corrigez puis réessayez.
          </Banner>
        )}

        <form onSubmit={soumettre} noValidate aria-label="Nouvelle année scolaire" style={{ maxWidth: '860px' }}>
          <Section title="1 · L'année" headingLevel={2}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: ADMIN_SPACING.lg }}>
              <AdminFormField label="Libellé" htmlFor="annee-libelle" required error={erreurs.libelle || (libelleExiste ? `Une année ${libelle} existe déjà.` : undefined)} description="Proposé : année suivante de la dernière année existante.">
                <AdminInput
                  id="annee-libelle"
                  value={form.libelle}
                  placeholder="2027-2028"
                  error={erreurs.libelle || libelleExiste}
                  onChange={(e) => maj('libelle', e.target.value)}
                />
              </AdminFormField>
              <AdminFormField label="Statut à la création" htmlFor="annee-statut">
                <AdminSelect
                  id="annee-statut"
                  value={statut}
                  onChange={(e) => setStatut(e.target.value)}
                  options={[
                    { value: 'active', label: 'Active (proposée à la création de classes)' },
                    { value: 'brouillon', label: 'Brouillon (en préparation)' },
                  ]}
                />
              </AdminFormField>
            </div>
            <p style={{ margin: `0 0 ${ADMIN_SPACING.lg}`, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
              L'année s'étend <strong>du début de la période 1 à la fin de la période 2</strong>
              {form.p1_debut && form.p2_fin ? ` : ${formatDate(form.p1_debut)} → ${formatDate(form.p2_fin)}` : ''}.
            </p>

            {[1, 2].map((n) => (
              <fieldset
                key={n}
                style={{ border: `1px solid ${ADMIN_COLORS.border}`, borderRadius: ADMIN_RADIUS.md, padding: ADMIN_SPACING.lg, margin: `0 0 ${ADMIN_SPACING.lg}` }}
              >
                <legend style={{ padding: `0 ${ADMIN_SPACING.sm}` }}>
                  <PeriodeBadge numero={n} /> <strong>Période {n}</strong>
                </legend>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: ADMIN_SPACING.lg }}>
                  {['debut', 'fin'].map((borne) => {
                    const champ = `p${n}_${borne}`;
                    return (
                      <AdminFormField key={champ} label={`P${n} ${borne === 'debut' ? 'du' : 'au'}`} htmlFor={`annee-${champ}`} required error={erreurs[champ]}>
                        <AdminInput id={`annee-${champ}`} type="date" value={form[champ]} error={erreurs[champ]} onChange={(e) => maj(champ, e.target.value)} />
                      </AdminFormField>
                    );
                  })}
                </div>
              </fieldset>
            ))}

            <FriseAnnee periodes={periodes} titre="Aperçu de l'année" />
            <div
              aria-live="polite"
              style={{
                marginTop: ADMIN_SPACING.md,
                padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
                borderRadius: ADMIN_RADIUS.md,
                background: ADMIN_TONES[ton].bg,
                color: ADMIN_TONES[ton].fg,
                border: `1px solid ${ADMIN_TONES[ton].border}`,
                fontSize: '14px',
              }}
            >
              {!complet && messagesBloquants.length === 0 ? (
                'Renseignez le libellé et les quatre dates : la cohérence est vérifiée en direct.'
              ) : messagesBloquants.length > 0 ? (
                messagesBloquants.map((m) => (
                  <div key={m}>
                    <strong>✖ Impossible :</strong> {m}{' '}
                    {chevauchee && m.startsWith('Cette année chevauche') && chevauchee.can?.update && (
                      <Link to={lienModifierPeriodes(chevauchee.id)}>Modifier {chevauchee.libelle}</Link>
                    )}
                  </div>
                ))
              ) : coherence.avertissements.length > 0 ? (
                coherence.avertissements.map((m) => (
                  <div key={m}>
                    <strong>⚠ Avertissement (non bloquant) :</strong> {m}
                  </div>
                ))
              ) : (
                <>
                  <strong>✓ Dates cohérentes</strong>
                  {coherence.info ? ` · ${coherence.info}` : ''}
                </>
              )}
            </div>
          </Section>

          {annees.data?.some((a) => a.can?.import_fwb) && (
            <div style={{ marginBottom: ADMIN_SPACING.lg }}>
              <AdminCheckbox
                id="annee-import-fwb"
                label={`Importer le calendrier FWB${libelle ? ` ${libelle}` : ''} à la création (vacances, jours fériés), si le fichier est disponible`}
                checked={importerFwb}
                onChange={(e) => setImporterFwb(e.target.checked)}
              />
            </div>
          )}

          <div style={{ display: 'flex', gap: ADMIN_SPACING.md, justifyContent: 'flex-end', flexWrap: 'wrap' }}>
            <LinkButton to={retour || '/admin/annees-scolaires'}>Annuler</LinkButton>
            <AdminButton type="submit" disabled={!complet || bloque} loading={envoi}>
              {libelle ? `Créer l'année ${libelle}` : "Créer l'année"}
            </AdminButton>
          </div>
        </form>
      </AdminPageContent>
    </>
  );
}
