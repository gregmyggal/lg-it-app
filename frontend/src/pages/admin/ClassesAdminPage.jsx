import { useEffect, useMemo } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import LinkButton from '../../components/ui/LinkButton';
import StatutBadge from '../../components/ui/StatutBadge';
import { Table, Th, Td, Tr } from '../../components/ui/Table';
import Banner from '../../components/ui/Banner';
import PeriodeBadge from '../../components/classes/PeriodeBadge';
import { FilterBar, FilterField } from '../../components/ui/Filters';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../../components/ui/DataStates';
import { anneeParDefaut, useAnneesScolaires } from '../../hooks/useAnneesScolaires';
import { useClasses } from '../../hooks/useClasses';
import { useCours } from '../../hooks/useCours';
import { lienNouvelleAnnee } from '../../utils/annees';
import { STATUTS_CLASSE, libelleAnneeListe, optionsStatut } from '../../utils/statuts';
import { JOURS_SEMAINE, libelleCreneau, formatDate, formatDateCourte, formatHeure, titreClasse, aujourdhuiISO } from '../../utils/dates';
import { ADMIN_COLORS } from '../../styles/AdminDesignSystem';

const NOUVELLE_ANNEE = '__nouvelle__';
const TOUTES_PERIODES = 'toutes';

/** Période mise en avant par défaut : celle qui couvre aujourd'hui, sinon la prochaine, sinon toutes. */
function periodeEnCours(annee) {
  const aujourdhui = aujourdhuiISO();
  const p = annee?.periodes.find((x) => x.date_debut <= aujourdhui && aujourdhui <= x.date_fin) || annee?.periodes.find((x) => x.date_fin >= aujourdhui);
  return p ? String(p.id) : '';
}

/** Écran « Classes » : liste des classes d'une année scolaire, filtrable (mock-up 01). */
export default function ClassesAdminPage() {
  const [params, setParams] = useSearchParams();
  const navigate = useNavigate();
  const annees = useAnneesScolaires();
  const cours = useCours();

  const listeAnnees = useMemo(() => annees.data || [], [annees.data]);
  const anneeId = params.get('annee_scolaire_id') || (anneeParDefaut(listeAnnees) ? String(anneeParDefaut(listeAnnees).id) : '');
  const annee = listeAnnees.find((a) => String(a.id) === anneeId);
  const periodeParam = params.get('periode_id');
  const filtres = {
    annee_scolaire_id: anneeId,
    periode_id: periodeParam === null ? periodeEnCours(annee) : periodeParam === TOUTES_PERIODES ? '' : periodeParam,
    cours_id: params.get('cours_id') || '',
    jour_semaine: params.get('jour_semaine') || '',
    statut: params.get('statut') || '',
  };
  const classes = useClasses(filtres, { enabled: Boolean(anneeId) });

  // La période sélectionnée doit appartenir à l'année choisie.
  useEffect(() => {
    if (annee && periodeParam && periodeParam !== TOUTES_PERIODES && !annee.periodes.some((p) => String(p.id) === periodeParam)) {
      majFiltre('periode_id', '');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [annee, periodeParam]);

  function majFiltre(cle, valeur) {
    const suivant = new URLSearchParams(params);
    if (cle === 'periode_id') suivant.set(cle, valeur || TOUTES_PERIODES);
    else if (valeur) suivant.set(cle, valeur);
    else suivant.delete(cle);
    if (cle === 'annee_scolaire_id') suivant.delete('periode_id');
    setParams(suivant, { replace: true });
  }

  function changerAnnee(valeur) {
    if (valeur === NOUVELLE_ANNEE) {
      navigate(lienNouvelleAnnee());
      return;
    }
    majFiltre('annee_scolaire_id', valeur);
  }

  const filtresActifs = Boolean(filtres.periode_id || filtres.cours_id || filtres.jour_semaine || filtres.statut);
  const numeroMisEnAvant = annee?.periodes.find((p) => String(p.id) === filtres.periode_id)?.numero || null;
  const periodesAffichees = numeroMisEnAvant ? [numeroMisEnAvant] : [1, 2];
  const aPlanifier = (classes.data || []).filter((c) => c.alerte_periode_2);
  const libelleAnnee = annee?.libelle || '';
  const optionsAnnee = [
    ...listeAnnees.map((a) => ({ value: String(a.id), label: libelleAnneeListe(a) })),
    { value: NOUVELLE_ANNEE, label: '＋ Nouvelle année scolaire…' },
  ];

  let contenu;
  if (annees.loading || (anneeId && classes.loading)) {
    contenu = <LoadingBlock message="Chargement des classes…" />;
  } else if (annees.error) {
    contenu = <ErrorBlock message="Impossible de charger les années scolaires. Vérifiez votre connexion puis réessayez." onRetry={annees.reload} />;
  } else if (!annee) {
    contenu = (
      <EmptyBlock
        icon="🏫"
        title="Aucune année scolaire"
        actions={
          <LinkButton to={lienNouvelleAnnee()} variant="primary">
            ＋ Créer une année scolaire
          </LinkButton>
        }
      >
        Une classe appartient à une année scolaire. Créez d'abord l'année (avec ses 2 périodes), puis ses classes.
      </EmptyBlock>
    );
  } else if (classes.error) {
    contenu = (
      <ErrorBlock
        message={`Impossible de charger les classes de ${libelleAnnee}. Vérifiez votre connexion puis réessayez.`}
        onRetry={classes.reload}
      />
    );
  } else if (classes.data.length === 0 && filtresActifs) {
    contenu = (
      <EmptyBlock
        icon="🔎"
        title={`Aucune classe ne correspond à ces filtres pour ${libelleAnnee}`}
        actions={
          <AdminButton
            variant="secondary"
            onClick={() => setParams(new URLSearchParams({ annee_scolaire_id: anneeId, periode_id: TOUTES_PERIODES }), { replace: true })}
          >
            Effacer les filtres
          </AdminButton>
        }
      >
        Modifiez ou effacez les filtres pour afficher les autres classes de l'année.
      </EmptyBlock>
    );
  } else if (classes.data.length === 0) {
    contenu = (
      <EmptyBlock
        icon="🏫"
        title={`Aucune classe pour ${libelleAnnee}`}
        actions={
          <>
            <LinkButton to={`/admin/classes/nouvelle?annee_scolaire_id=${anneeId}`} variant="primary">
              ＋ Créer la première classe
            </LinkButton>
            <LinkButton to="/admin/calendrier-scolaire">Voir le calendrier scolaire</LinkButton>
          </>
        }
      >
        L'année démarre sur un état propre : aucune classe n'a été créée. Choisissez un jour et un créneau, puis les 14 séances de la période 1 seront générées en respectant le calendrier scolaire ; vous pouvez ajouter la période 2 tout de suite ou plus tard.
      </EmptyBlock>
    );
  } else {
    contenu = (
      <>
        <p style={{ margin: '0 0 12px', fontSize: '13px', color: ADMIN_COLORS.textSecondary }} aria-live="polite">
          {classes.data.length} classe{classes.data.length > 1 ? 's' : ''}
        </p>
        {aPlanifier.length > 0 && (
          <Banner tone="warning">
            <strong>
              ⏰ {aPlanifier.length} classe{aPlanifier.length > 1 ? 's' : ''} à planifier :
            </strong>
            <ul style={{ margin: '4px 0 0', paddingLeft: '20px' }}>
              {aPlanifier.map((c) => (
                <li key={c.id}>
                  <Link to={`/admin/classes/${c.id}`}>
                    « {titreClasse(c)} — {libelleCreneau(c)} »
                  </Link>{' '}
                  : la période 1 se termine le {formatDate(c.alerte_periode_2.date_fin_periode_1)}
                  {Number.isFinite(c.alerte_periode_2.jours_restants) && c.alerte_periode_2.jours_restants >= 0
                    ? ` (dans ${c.alerte_periode_2.jours_restants} jour${c.alerte_periode_2.jours_restants > 1 ? 's' : ''})`
                    : ''}{' '}
                  et aucune période 2 n'est planifiée.
                </li>
              ))}
            </ul>
          </Banner>
        )}
        <Table caption={`Classes de ${libelleAnnee}`} minWidth="920px">
          <thead>
            <tr>
              <Th>Classe / créneau</Th>
              {periodesAffichees.map((n) => (
                <Th key={n}>Période {n} · cours</Th>
              ))}
              <Th>Lieu</Th>
              <Th>Prochaine session</Th>
              <Th>Statut</Th>
              <Th srOnly>Actions</Th>
            </tr>
          </thead>
          <tbody>
            {classes.data.map((c) => (
              <Tr key={c.id}>
                <Td>
                  <strong>{titreClasse(c)}</strong>
                  <div style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>{libelleCreneau(c)}</div>
                </Td>
                {periodesAffichees.map((n) => (
                  <Td key={n}>
                    <CellulePeriode classe={c} numero={n} />
                  </Td>
                ))}
                <Td>{c.lieu || '—'}</Td>
                <Td>
                  {c.prochaine_session
                    ? `${c.prochaine_session.libelle} · ${formatDateCourte(c.prochaine_session.date)} · ${formatHeure(c.heure_debut)}`
                    : '—'}
                </Td>
                <Td>
                  <StatutBadge table={STATUTS_CLASSE} valeur={c.statut} />
                </Td>
                <Td>
                  <LinkButton to={`/admin/classes/${c.id}`} size="sm">
                    Ouvrir la classe
                  </LinkButton>
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      </>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="🏫"
        title="Classes"
        badge={libelleAnnee || undefined}
        breadcrumb={<>Scolarité › Classes</>}
        description="Une classe regroupe jusqu'à 2 périodes (14 séances chacune) sur un même créneau hebdomadaire."
        action={
          anneeId ? (
            <LinkButton to={`/admin/classes/nouvelle?annee_scolaire_id=${anneeId}`} variant="primary">
              ＋ Nouvelle classe
            </LinkButton>
          ) : undefined
        }
      />

      <AdminPageContent>
        <FilterBar label="Filtres des classes">
          <FilterField
            id="filtre-annee"
            label="Année scolaire"
            value={anneeId}
            onChange={changerAnnee}
            options={optionsAnnee}
            disabled={annees.loading}
          />
          <FilterField
            id="filtre-periode"
            label="Période"
            value={filtres.periode_id || TOUTES_PERIODES}
            onChange={(v) => majFiltre('periode_id', v === TOUTES_PERIODES ? '' : v)}
            options={[
              ...(annee?.periodes || []).map((p) => ({ value: String(p.id), label: `Période ${p.numero}${String(p.id) === periodeEnCours(annee) ? ' (en cours)' : ''}` })),
              { value: TOUTES_PERIODES, label: 'Toutes les périodes' },
            ]}
          />
          <FilterField
            id="filtre-cours"
            label="Cours"
            value={filtres.cours_id}
            onChange={(v) => majFiltre('cours_id', v)}
            placeholder="Tous les cours (P1 et P2)"
            options={(cours.data || []).map((c) => ({ value: String(c.id), label: c.titre }))}
          />
          <FilterField
            id="filtre-jour"
            label="Jour"
            value={filtres.jour_semaine}
            onChange={(v) => majFiltre('jour_semaine', v)}
            placeholder="Tous les jours"
            options={JOURS_SEMAINE.map((j) => ({ value: String(j.value), label: j.label }))}
          />
          <FilterField
            id="filtre-statut"
            label="Statut"
            value={filtres.statut}
            onChange={(v) => majFiltre('statut', v)}
            placeholder="Tous les statuts"
            options={optionsStatut(STATUTS_CLASSE)}
          />
        </FilterBar>

        {contenu}

        {annee && (
          <p style={{ marginTop: 16, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
            <Link to="/admin/calendrier-scolaire">Voir le calendrier scolaire {libelleAnnee}</Link>
          </p>
        )}
      </AdminPageContent>
    </>
  );
}

/** Cellule d'une période dans la liste : « P1 Scratch », dates, séances, hors période — ou « à planifier » / « à ajouter ». */
function CellulePeriode({ classe, numero }) {
  const p = (classe.periodes || []).find((x) => x.numero === numero);
  if (!p) {
    const alerte = numero === 2 && classe.alerte_periode_2;
    return (
      <span style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
        <PeriodeBadge numero={numero} /> {numero === 2 ? (alerte ? '⏰ P2 à planifier' : 'P2 à planifier') : 'P1 à ajouter'}
      </span>
    );
  }
  return (
    <div style={{ fontSize: '13px' }}>
      <PeriodeBadge numero={numero} /> <strong>{p.cours?.titre}</strong>
      <div style={{ color: ADMIN_COLORS.textSecondary }}>
        {p.nb_sessions} séance{p.nb_sessions > 1 ? 's' : ''}
        {p.date_premiere_session ? ` · ${formatDateCourte(p.date_premiere_session)}${p.date_derniere_session ? ` → ${formatDateCourte(p.date_derniere_session)}` : ''}` : ''}
      </div>
      {p.nb_hors_periode > 0 && <StatutBadge label={`⚠ ${p.nb_hors_periode} hors période`} tone="warning" />}{' '}
      {p.statut === 'annulee' && <StatutBadge label="Annulée" tone="neutral" />}
    </div>
  );
}
