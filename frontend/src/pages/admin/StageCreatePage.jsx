import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import { AdminFormField, AdminInput, AdminSelect } from '../../components/AdminFormField';
import Banner from '../../components/ui/Banner';
import StatutBadge from '../../components/ui/StatutBadge';
import { Section } from '../../components/ui/Card';
import { apercuStage, creerStage, useThemesStage } from '../../hooks/useClasses';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { formatDuree } from '../../utils/format';
import { formatDate, formatDateCourte, formatHeure, nomJour } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const DELAI_APERCU_MS = 400;
const CLE_LIEU = 'stage-dernier-lieu';
const DEMI_JOURNEES = [
  { value: 'matin', label: 'Matin' },
  { value: 'apres_midi', label: 'Après-midi' },
];
const CRENEAUX_DEFAUT = {
  matin: { heure_debut: '09:00', heure_fin: '12:00' },
  apres_midi: { heure_debut: '13:30', heure_fin: '16:30' },
};

function dernierLieu() {
  try {
    return localStorage.getItem(CLE_LIEU) || '';
  } catch {
    return '';
  }
}

function retenirLieu(lieu) {
  try {
    if (lieu) localStorage.setItem(CLE_LIEU, lieu);
  } catch {
    // stockage indisponible : la reprise du dernier lieu est un simple confort
  }
}

/**
 * Écran « Nouveau stage » (STG-01) : un cours, une semaine, puis la grille lundi → vendredi (jour coché ou non,
 * matin / après-midi). Les jours fériés et fermetures sont décochés par le serveur ; l'aperçu se recalcule en direct.
 */
export default function StageCreatePage() {
  const navigate = useNavigate();
  const toast = useToast();
  const themes = useThemesStage();
  const [form, setForm] = useState({ stage_id: '', date_debut: '', lieu: dernierLieu(), demi_journee_defaut: 'matin' });
  const [creneaux, setCreneaux] = useState(CRENEAUX_DEFAUT);
  const [autreHoraire, setAutreHoraire] = useState(false);
  // null = choix par défaut du serveur ; sinon { [date]: { actif, demi_journee } } dès que l'utilisateur coche ou change un jour.
  const [selection, setSelection] = useState(null);
  const [apercu, setApercu] = useState({ data: null, loading: false, erreurs: {}, message: null });
  const [envoi, setEnvoi] = useState(false);
  const [echec, setEchec] = useState(null);
  const sequence = useRef(0);

  const payload = useMemo(() => {
    const base = { date_debut: form.date_debut, demi_journee_defaut: form.demi_journee_defaut, creneaux };
    if (form.stage_id) base.stage_id = Number(form.stage_id);
    if (selection) {
      base.jours = Object.entries(selection)
        .filter(([, j]) => j.actif)
        .map(([date, j]) => ({ date, demi_journee: j.demi_journee }));
    }
    return base;
  }, [form.date_debut, form.demi_journee_defaut, form.stage_id, creneaux, selection]);

  useEffect(() => {
    if (!form.date_debut) {
      setApercu({ data: null, loading: false, erreurs: {}, message: null });
      return undefined;
    }
    const numero = ++sequence.current;
    setApercu((prev) => ({ ...prev, loading: true }));
    const minuteur = setTimeout(() => {
      apercuStage(payload)
        .then((data) => numero === sequence.current && setApercu({ data, loading: false, erreurs: {}, message: null }))
        .catch((err) => {
          if (numero !== sequence.current) return;
          setApercu({ data: null, loading: false, erreurs: getFieldErrors(err), message: getErrorMessage(err, "Impossible de calculer l'aperçu.") });
        });
    }, DELAI_APERCU_MS);
    return () => clearTimeout(minuteur);
  }, [payload, form.date_debut]);

  function maj(champ, valeur) {
    setForm((prev) => ({ ...prev, [champ]: valeur }));
    if (champ === 'date_debut' || champ === 'demi_journee_defaut') setSelection(null);
    setEchec(null);
  }

  function majCreneau(demi, champ, valeur) {
    setCreneaux((prev) => ({ ...prev, [demi]: { ...prev[demi], [champ]: valeur } }));
  }

  /** Part de la grille renvoyée par le serveur pour ne figer que les jours modifiés par l'utilisateur. */
  function modifierJour(date, changement) {
    const grille = Object.fromEntries((apercu.data?.jours || []).map((j) => [j.date, { actif: j.actif, demi_journee: j.demi_journee }]));
    setSelection({ ...(selection || grille), [date]: { ...(selection || grille)[date], ...changement } });
    setEchec(null);
  }

  const plan = apercu.data;
  const peutCreer = Boolean(form.stage_id && form.date_debut && plan && !plan.blocage && plan.nb_seances > 0 && !envoi && !apercu.loading);

  async function soumettre(e) {
    e.preventDefault();
    if (!peutCreer) return;
    setEnvoi(true);
    setEchec(null);
    try {
      const classe = await creerStage({ ...payload, jours: payload.jours || plan.jours.filter((j) => j.actif).map((j) => ({ date: j.date, demi_journee: j.demi_journee })), lieu: form.lieu || null });
      retenirLieu(form.lieu);
      toast.success(`Stage créé (${classe.nb_sessions} séance${classe.nb_sessions > 1 ? 's' : ''}). Vous pouvez maintenant assigner des professeurs.`);
      navigate(`/admin/classes/${classe.id}#professeurs`);
    } catch (err) {
      setEchec({ message: getErrorMessage(err, "Le stage n'a pas pu être créé."), champs: getFieldErrors(err) });
    } finally {
      setEnvoi(false);
    }
  }

  const erreur = (champ) => echec?.champs?.[champ] || apercu.erreurs?.[champ];

  return (
    <>
      <AdminPageHeader
        icon="🏕️"
        title="Nouveau stage"
        breadcrumb={<>Scolarité › Classes & stages › Nouveau stage</>}
        description="Un stage tient sur une semaine : jusqu'à 5 séances d'une demi-journée, un jour pouvant être décoché."
      />
      <AdminPageContent>
        <form onSubmit={soumettre} noValidate style={{ display: 'grid', gap: ADMIN_SPACING.lg }}>
          {echec?.message && <Banner tone="error">{echec.message}</Banner>}

          <Section title="1 · Stage" headingLevel={2}>
            <AdminFormField
              label="Thème du stage"
              htmlFor="stage-theme"
              required
              description={
                themes.error ? (
                  'Impossible de charger les thèmes de stage.'
                ) : themes.data?.length === 0 ? (
                  <>
                    Aucun thème de stage : créez-en un dans <Link to="/admin/stages">Contenu › Stages</Link>.
                  </>
                ) : undefined
              }
              error={erreur('stage_id')}
            >
              <AdminSelect
                id="stage-theme"
                value={form.stage_id}
                onChange={(e) => maj('stage_id', e.target.value)}
                placeholder="Choisir un thème"
                options={(themes.data || []).map((t) => ({ value: String(t.id), label: `${t.titre}${t.statut === 'draft' ? ' (brouillon)' : ''}` }))}
                error={Boolean(erreur('stage_id'))}
              />
            </AdminFormField>
            <AdminFormField
              label="Semaine"
              htmlFor="stage-semaine"
              required
              description={plan ? `Du ${formatDate(plan.date_debut)} au ${formatDate(plan.date_fin)} (ramenée au lundi)` : 'Choisissez un jour de la semaine : elle est ramenée au lundi.'}
              error={erreur('date_debut')}
            >
              <AdminInput id="stage-semaine" type="date" value={form.date_debut} onChange={(e) => maj('date_debut', e.target.value)} error={Boolean(erreur('date_debut'))} />
            </AdminFormField>
            <AdminFormField label="Lieu" htmlFor="stage-lieu" error={erreur('lieu')}>
              <AdminInput id="stage-lieu" value={form.lieu} onChange={(e) => maj('lieu', e.target.value)} placeholder="Salle A" />
            </AdminFormField>
            <AdminFormField label="Créneau par défaut" htmlFor="stage-defaut">
              <AdminSelect
                id="stage-defaut"
                value={form.demi_journee_defaut}
                onChange={(e) => maj('demi_journee_defaut', e.target.value)}
                options={DEMI_JOURNEES.map((d) => ({ value: d.value, label: `${d.label} ${formatHeure(creneaux[d.value].heure_debut)}–${formatHeure(creneaux[d.value].heure_fin)}` }))}
              />
            </AdminFormField>
            <AdminButton type="button" variant="secondary" size="sm" onClick={() => setAutreHoraire((v) => !v)} aria-expanded={autreHoraire}>
              {autreHoraire ? 'Masquer les horaires' : 'Autre horaire…'}
            </AdminButton>
            {autreHoraire && (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: ADMIN_SPACING.md, marginTop: ADMIN_SPACING.md }}>
                {DEMI_JOURNEES.map((d) =>
                  ['heure_debut', 'heure_fin'].map((champ) => (
                    <AdminFormField key={`${d.value}-${champ}`} label={`${d.label} · ${champ === 'heure_debut' ? 'début' : 'fin'}`} htmlFor={`stage-${d.value}-${champ}`} error={erreur(`creneaux.${d.value}.${champ}`)}>
                      <AdminInput id={`stage-${d.value}-${champ}`} type="time" value={creneaux[d.value][champ]} onChange={(e) => majCreneau(d.value, champ, e.target.value)} />
                    </AdminFormField>
                  )),
                )}
              </div>
            )}
          </Section>

          <Section title="2 · Jours" subtitle="Décochez un jour sans séance ; choisissez matin ou après-midi pour chaque jour." headingLevel={2}>
            {!plan ? (
              <p style={{ margin: 0, color: ADMIN_COLORS.textSecondary }} role="status">
                {apercu.loading ? 'Calcul de la semaine…' : apercu.message || 'Choisissez une semaine : la grille des 5 jours s\'affiche ici.'}
              </p>
            ) : (
              <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: ADMIN_SPACING.md }}>
                {plan.jours.map((j) => (
                  <li key={j.date} style={{ display: 'flex', flexWrap: 'wrap', gap: ADMIN_SPACING.md, alignItems: 'center' }}>
                    <label style={{ display: 'inline-flex', gap: ADMIN_SPACING.sm, alignItems: 'center', minWidth: '150px' }}>
                      <input type="checkbox" checked={j.actif} onChange={(e) => modifierJour(j.date, { actif: e.target.checked })} style={{ width: 18, height: 18 }} />
                      <span>
                        {nomJour(j.jour_semaine)} {formatDate(j.date).slice(0, 5)}
                      </span>
                    </label>
                    <select
                      aria-label={`Demi-journée du ${nomJour(j.jour_semaine)}`}
                      value={j.demi_journee}
                      disabled={!j.actif}
                      onChange={(e) => modifierJour(j.date, { demi_journee: e.target.value })}
                    >
                      {DEMI_JOURNEES.map((d) => (
                        <option key={d.value} value={d.value}>
                          {d.label}
                        </option>
                      ))}
                    </select>
                    {j.conge && <StatutBadge label={j.ferme ? `${j.conge.type === 'ferie' ? 'Férié' : 'Fermeture'} · ${j.conge.libelle}` : `Vacances · ${j.conge.libelle}`} tone={j.ferme ? 'warning' : 'neutral'} />}
                  </li>
                ))}
              </ul>
            )}
          </Section>

          <Section title={plan?.nb_seances ? `Aperçu des ${plan.nb_seances} séance${plan.nb_seances > 1 ? 's' : ''}` : 'Aperçu des séances'} headingLevel={2}>
            <div aria-live="polite" aria-busy={apercu.loading}>
              {plan?.blocage && <Banner tone="error">{plan.blocage.message}</Banner>}
              {(plan?.avertissements || []).map((a) => (
                <Banner key={a} tone="warning">
                  ⚠ {a}
                </Banner>
              ))}
              {plan?.seances?.length > 0 ? (
                <>
                  <ol style={{ margin: 0, paddingLeft: ADMIN_SPACING.xl }}>
                    {plan.seances.map((s) => (
                      <li key={s.date}>
                        Séance {s.seance_numero} · {formatDateCourte(s.date)} · {s.demi_journee === 'matin' ? 'Matin' : 'Après-midi'} {formatHeure(s.heure_debut)}–{formatHeure(s.heure_fin)}
                      </li>
                    ))}
                  </ol>
                  <p style={{ margin: `${ADMIN_SPACING.md} 0 0`, color: ADMIN_COLORS.textSecondary }}>
                    {plan.nb_seances} séance{plan.nb_seances > 1 ? 's' : ''} · {formatDuree(plan.duree_totale_minutes / 60)} au total
                  </p>
                </>
              ) : (
                !plan?.blocage && <p style={{ margin: 0, color: ADMIN_COLORS.textSecondary }}>Les séances s'affichent ici dès qu'une semaine est choisie.</p>
              )}
            </div>
          </Section>

          <div style={{ display: 'flex', gap: ADMIN_SPACING.md, justifyContent: 'flex-end', flexWrap: 'wrap' }}>
            <AdminButton type="button" variant="secondary" onClick={() => navigate('/admin/classes')}>
              Annuler
            </AdminButton>
            <AdminButton type="submit" variant="primary" disabled={!peutCreer} loading={envoi}>
              {plan?.nb_seances ? `Créer le stage (${plan.nb_seances} séance${plan.nb_seances > 1 ? 's' : ''})` : 'Créer le stage'}
            </AdminButton>
          </div>
        </form>
      </AdminPageContent>
    </>
  );
}
