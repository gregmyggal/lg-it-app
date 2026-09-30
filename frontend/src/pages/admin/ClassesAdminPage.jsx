import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import LinkButton from '../../components/ui/LinkButton';
import StatutBadge from '../../components/ui/StatutBadge';
import { Table, Th, Td, Tr } from '../../components/ui/Table';
import { FilterBar, FilterField } from '../../components/ui/Filters';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../../components/ui/DataStates';
import AnneeScolaireModal from '../../components/classes/AnneeScolaireModal';
import { anneeParDefaut, useAnneesScolaires } from '../../hooks/useAnneesScolaires';
import { useClasses } from '../../hooks/useClasses';
import { useCours } from '../../hooks/useCours';
import { STATUTS_CLASSE, libelleAnneeListe, optionsStatut } from '../../utils/statuts';
import { JOURS_SEMAINE, libelleCreneau, formatDateCourte, formatHeure } from '../../utils/dates';
import { ADMIN_COLORS } from '../../styles/AdminDesignSystem';

const NOUVELLE_ANNEE = '__nouvelle__';

/** Écran « Classes » : liste des classes d'une année scolaire, filtrable (mock-up 01). */
export default function ClassesAdminPage() {
  const [params, setParams] = useSearchParams();
  const [modaleAnnee, setModaleAnnee] = useState(false);
  const annees = useAnneesScolaires();
  const cours = useCours();

  const listeAnnees = useMemo(() => annees.data || [], [annees.data]);
  const anneeId = params.get('annee_scolaire_id') || (anneeParDefaut(listeAnnees) ? String(anneeParDefaut(listeAnnees).id) : '');
  const annee = listeAnnees.find((a) => String(a.id) === anneeId);
  const filtres = {
    annee_scolaire_id: anneeId,
    periode_id: params.get('periode_id') || '',
    cours_id: params.get('cours_id') || '',
    jour_semaine: params.get('jour_semaine') || '',
    statut: params.get('statut') || '',
  };
  const classes = useClasses(filtres, { enabled: Boolean(anneeId) });

  // La période sélectionnée doit appartenir à l'année choisie.
  useEffect(() => {
    if (annee && filtres.periode_id && !annee.periodes.some((p) => String(p.id) === filtres.periode_id)) {
      majFiltre('periode_id', '');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [annee, filtres.periode_id]);

  function majFiltre(cle, valeur) {
    const suivant = new URLSearchParams(params);
    if (valeur) suivant.set(cle, valeur);
    else suivant.delete(cle);
    if (cle === 'annee_scolaire_id') suivant.delete('periode_id');
    setParams(suivant, { replace: true });
  }

  function changerAnnee(valeur) {
    if (valeur === NOUVELLE_ANNEE) {
      setModaleAnnee(true);
      return;
    }
    majFiltre('annee_scolaire_id', valeur);
  }

  const filtresActifs = Boolean(filtres.periode_id || filtres.cours_id || filtres.jour_semaine || filtres.statut);
  const libelleAnnee = annee?.libelle || '';
  const optionsAnnee = [
    ...listeAnnees.map((a) => ({ value: String(a.id), label: libelleAnneeListe(a) })),
    { value: NOUVELLE_ANNEE, label: '＋ Nouvelle année scolaire…' },
  ];
  const peutImporterFwb = listeAnnees.some((a) => a.can?.import_fwb);

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
          <AdminButton onClick={() => setModaleAnnee(true)}>＋ Créer une année scolaire</AdminButton>
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
            onClick={() => setParams(new URLSearchParams({ annee_scolaire_id: anneeId }), { replace: true })}
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
        L'année démarre sur un état propre : aucune classe n'a été créée. Choisissez un cours du catalogue, un jour
        et un créneau : les 14 sessions seront générées en respectant le calendrier scolaire.
      </EmptyBlock>
    );
  } else {
    contenu = (
      <>
        <p style={{ margin: '0 0 12px', fontSize: '13px', color: ADMIN_COLORS.textSecondary }} aria-live="polite">
          {classes.data.length} classe{classes.data.length > 1 ? 's' : ''}
        </p>
        <Table caption={`Classes de ${libelleAnnee}`} minWidth="820px">
          <thead>
            <tr>
              <Th>Cours / créneau</Th>
              <Th>Période</Th>
              <Th>Lieu</Th>
              <Th>Sessions</Th>
              <Th>Prochaine session</Th>
              <Th>Statut</Th>
              <Th srOnly>Actions</Th>
            </tr>
          </thead>
          <tbody>
            {classes.data.map((c) => (
              <Tr key={c.id}>
                <Td>
                  <strong>{c.cours?.titre}</strong>
                  <div style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>{libelleCreneau(c)}</div>
                </Td>
                <Td>Période {c.periode?.numero}</Td>
                <Td>{c.lieu || '—'}</Td>
                <Td>
                  {c.nb_sessions} session{c.nb_sessions > 1 ? 's' : ''}
                </Td>
                <Td>
                  {c.prochaine_session
                    ? `${formatDateCourte(c.prochaine_session.date)} · ${formatHeure(c.heure_debut)}`
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
        description="Organisation des cours du catalogue par année scolaire, période et créneau hebdomadaire."
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
            value={filtres.periode_id}
            onChange={(v) => majFiltre('periode_id', v)}
            placeholder="Toutes"
            options={(annee?.periodes || []).map((p) => ({ value: String(p.id), label: `Période ${p.numero}` }))}
          />
          <FilterField
            id="filtre-cours"
            label="Cours"
            value={filtres.cours_id}
            onChange={(v) => majFiltre('cours_id', v)}
            placeholder="Tous les cours"
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

      <AnneeScolaireModal
        isOpen={modaleAnnee}
        onClose={() => setModaleAnnee(false)}
        peutImporterFwb={peutImporterFwb}
        onCreated={(nouvelle) => {
          setModaleAnnee(false);
          annees.reload();
          majFiltre('annee_scolaire_id', String(nouvelle.id));
        }}
      />
    </>
  );
}
