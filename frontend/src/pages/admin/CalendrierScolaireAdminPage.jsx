import { useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import AdminModal from '../../components/AdminModal';
import Banner from '../../components/ui/Banner';
import StatutBadge from '../../components/ui/StatutBadge';
import { Table, Th, Td, Tr } from '../../components/ui/Table';
import { FilterBar, FilterField } from '../../components/ui/Filters';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../../components/ui/DataStates';
import LinkButton from '../../components/ui/LinkButton';
import PeriodeBadge from '../../components/classes/PeriodeBadge';
import CalendrierEntreeModal from '../../components/calendrier/CalendrierEntreeModal';
import { anneeParDefaut, useAnneesScolaires, importerCalendrierFwb } from '../../hooks/useAnneesScolaires';
import { useCalendrierScolaire, supprimerEntreeCalendrier } from '../../hooks/useCalendrierScolaire';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage } from '../../api/errors';
import {
  TYPES_CALENDRIER,
  SOURCES_CALENDRIER,
  estSourceMasquable,
  getStatut,
  libelleAnneeListe,
  optionsStatut,
} from '../../utils/statuts';
import { formatPlage } from '../../utils/dates';
import { lienModifierPeriodes, lienNouvelleAnnee } from '../../utils/annees';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const NOUVELLE_ANNEE = '__nouvelle__';

/** Écran « Calendrier scolaire » : vacances, fériés et fermetures d'une année (mock-up 05). */
export default function CalendrierScolaireAdminPage() {
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const annees = useAnneesScolaires();
  const listeAnnees = useMemo(() => annees.data || [], [annees.data]);
  const anneeId = params.get('annee_scolaire_id') || (anneeParDefaut(listeAnnees) ? String(anneeParDefaut(listeAnnees).id) : '');
  const annee = listeAnnees.find((a) => String(a.id) === anneeId);
  const filtres = { type: params.get('type') || '', source: params.get('source') || '' };
  const calendrier = useCalendrierScolaire(anneeId, filtres);

  const navigate = useNavigate();
  const [entreeModale, setEntreeModale] = useState(null); // { entree | null }
  const [aSupprimer, setASupprimer] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const [dernierImport, setDernierImport] = useState(null);

  function majFiltre(cle, valeur) {
    const suivant = new URLSearchParams(params);
    if (valeur) suivant.set(cle, valeur);
    else suivant.delete(cle);
    setParams(suivant, { replace: true });
  }

  function changerAnnee(valeur) {
    if (valeur === NOUVELLE_ANNEE) {
      navigate(lienNouvelleAnnee({ retour: '/admin/calendrier-scolaire' }));
      return;
    }
    setParams(valeur ? new URLSearchParams({ annee_scolaire_id: valeur }) : new URLSearchParams(), { replace: true });
    setDernierImport(null);
  }

  async function importer() {
    setEnvoi(true);
    try {
      const resultat = await importerCalendrierFwb(annee.id);
      toast.success(resultat.message);
      setDernierImport({ ...resultat, anneeId: annee.id });
      calendrier.reload();
    } catch (err) {
      toast.error(getErrorMessage(err, "L'import du calendrier FWB a échoué."));
    } finally {
      setEnvoi(false);
    }
  }

  async function supprimer() {
    setEnvoi(true);
    try {
      await supprimerEntreeCalendrier(aSupprimer.id);
      toast.success(
        estSourceMasquable(aSupprimer.source)
          ? `« ${aSupprimer.libelle} » supprimée du calendrier ${annee.libelle}. Elle reste masquée lors des prochains imports FWB.`
          : `« ${aSupprimer.libelle} » supprimée du calendrier ${annee.libelle}.`,
      );
      setASupprimer(null);
      calendrier.reload();
    } catch (err) {
      toast.error(getErrorMessage(err, "La date n'a pas pu être supprimée."));
    } finally {
      setEnvoi(false);
    }
  }

  const filtresActifs = Boolean(filtres.type || filtres.source);
  const libelleAnnee = annee?.libelle || '';
  const peutImporter = Boolean(annee?.can?.import_fwb);
  const entrees = calendrier.data || [];
  const nbParSource = (source) => entrees.filter((e) => e.source === source).length;

  let contenu;
  if (annees.loading || (anneeId && !calendrier.data && !calendrier.error)) {
    contenu = <LoadingBlock message="Chargement du calendrier scolaire…" />;
  } else if (annees.error) {
    contenu = <ErrorBlock message="Impossible de charger les années scolaires. Réessayez." onRetry={annees.reload} />;
  } else if (!annee) {
    contenu = (
      <EmptyBlock
        icon="📅"
        title="Aucune année scolaire"
        actions={
          <LinkButton to={lienNouvelleAnnee({ retour: '/admin/calendrier-scolaire' })} variant="primary">
            Créer une année scolaire
          </LinkButton>
        }
      >
        Le calendrier scolaire appartient à une année. Créez d'abord l'année scolaire (avec ses 2 périodes).
      </EmptyBlock>
    );
  } else if (calendrier.error) {
    contenu = (
      <ErrorBlock message={`Impossible de charger le calendrier scolaire ${libelleAnnee}. Réessayez.`} onRetry={calendrier.reload} />
    );
  } else if (entrees.length === 0 && filtresActifs) {
    contenu = (
      <EmptyBlock
        icon="🔎"
        title={`Aucune date pour ces filtres dans le calendrier ${libelleAnnee}`}
        actions={
          <AdminButton variant="secondary" onClick={() => setParams(new URLSearchParams({ annee_scolaire_id: anneeId }), { replace: true })}>
            Effacer les filtres
          </AdminButton>
        }
      >
        Modifiez ou effacez les filtres pour afficher les autres dates.
      </EmptyBlock>
    );
  } else if (entrees.length === 0) {
    contenu = (
      <EmptyBlock
        icon="📅"
        title={`Le calendrier scolaire ${libelleAnnee} est vide`}
        actions={
          <>
            {peutImporter && (
              <AdminButton onClick={importer} loading={envoi}>
                Importer le calendrier FWB {libelleAnnee}
              </AdminButton>
            )}
            <AdminButton variant="secondary" onClick={() => setEntreeModale({ entree: null })}>
              Ajouter une date à la main
            </AdminButton>
          </>
        }
      >
        Importez le calendrier officiel de la FWB : vous pourrez ensuite y ajouter les fermetures propres à l'école.
        Sans calendrier, la création d'une classe ne saute aucune vacance.
        {!peutImporter && (
          <>
            <br />
            <strong>
              Réservé aux administrateurs. Vous êtes directeur : demandez l'import à un administrateur, ou ajoutez vos
              dates à la main.
            </strong>
          </>
        )}
      </EmptyBlock>
    );
  } else {
    contenu = (
      <>
        <div
          style={{ display: 'flex', flexWrap: 'wrap', gap: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.lg}`, alignItems: 'center', margin: `0 0 ${ADMIN_SPACING.md}`, fontSize: '13px' }}
          aria-label="Légende"
        >
          <span>
            Type :{' '}
            {Object.keys(TYPES_CALENDRIER).map((valeur) => (
              <span key={valeur} style={{ marginRight: ADMIN_SPACING.sm }}>
                <StatutBadge table={TYPES_CALENDRIER} valeur={valeur} />
              </span>
            ))}
          </span>
          <span style={{ color: ADMIN_COLORS.textSecondary }}>
            Source : <StatutBadge table={SOURCES_CALENDRIER} valeur="fwb" /> <StatutBadge table={SOURCES_CALENDRIER} valeur="ecole" />
          </span>
        </div>
        <h2 style={{ margin: `0 0 ${ADMIN_SPACING.xs}`, fontSize: '18px' }}>Dates du calendrier</h2>
        <p style={{ margin: `0 0 ${ADMIN_SPACING.md}`, fontSize: '13px', color: ADMIN_COLORS.textSecondary }} aria-live="polite">
          {entrees.length} entrée{entrees.length > 1 ? 's' : ''} · {nbParSource('fwb')} FWB · {nbParSource('ecole')} École
        </p>
        <Table caption={`Dates du calendrier scolaire ${libelleAnnee}`} minWidth="720px" cards>
          <thead>
            <tr>
              <Th>Période</Th>
              <Th>Type</Th>
              <Th>Libellé</Th>
              <Th>Source</Th>
              <Th srOnly>Actions</Th>
            </tr>
          </thead>
          <tbody>
            {entrees.map((e) => (
              <Tr key={e.id}>
                <Td label="Période" style={{ whiteSpace: 'nowrap' }}>{formatPlage(e.date_debut, e.date_fin)}</Td>
                <Td label="Type">
                  <StatutBadge table={TYPES_CALENDRIER} valeur={e.type} />
                </Td>
                <Td label="Libellé">{e.libelle}</Td>
                <Td label="Source">
                  {e.modifie_manuellement ? (
                    <StatutBadge
                      label={`${getStatut(SOURCES_CALENDRIER, e.source).label} · modifiée`}
                      tone={getStatut(SOURCES_CALENDRIER, e.source).tone}
                    />
                  ) : (
                    <StatutBadge table={SOURCES_CALENDRIER} valeur={e.source} />
                  )}
                </Td>
                <Td label="Actions">
                  <div style={{ display: 'flex', gap: ADMIN_SPACING.sm }}>
                    {e.can?.update && (
                      <AdminButton size="sm" variant="secondary" onClick={() => setEntreeModale({ entree: e })} aria-label={`Modifier « ${e.libelle} »`}>
                        Modifier
                      </AdminButton>
                    )}
                    {e.can?.delete && (
                      <AdminButton size="sm" variant="secondary" onClick={() => setASupprimer(e)} aria-label={`Supprimer « ${e.libelle} »`}>
                        Supprimer
                      </AdminButton>
                    )}
                  </div>
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
        <p style={{ marginTop: ADMIN_SPACING.lg, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
          Les entrées École et vos modifications ne sont jamais écrasées par un nouvel import FWB.{' '}
          <Link to={`/admin/classes?annee_scolaire_id=${anneeId}`}>Voir les classes de {libelleAnnee}</Link>
        </p>
      </>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="📅"
        title="Calendrier scolaire"
        badge={libelleAnnee || undefined}
        breadcrumb={<>Scolarité › Calendrier scolaire</>}
        description="Vacances, jours fériés et fermetures qui déterminent les dates des sessions."
        action={
          <>
            {peutImporter && (
              <AdminButton variant="secondary" onClick={importer} loading={envoi}>
                Importer le calendrier FWB
              </AdminButton>
            )}
            <LinkButton to={lienNouvelleAnnee({ retour: '/admin/calendrier-scolaire' })}>Nouvelle année scolaire</LinkButton>
            {annee && <AdminButton onClick={() => setEntreeModale({ entree: null })}>＋ Ajouter une date</AdminButton>}
          </>
        }
      />

      <AdminPageContent>
        <FilterBar label="Filtres du calendrier scolaire">
          <FilterField
            id="cal-annee"
            label="Année scolaire"
            value={anneeId}
            onChange={changerAnnee}
            options={[
              ...listeAnnees.map((a) => ({ value: String(a.id), label: libelleAnneeListe(a) })),
              { value: NOUVELLE_ANNEE, label: '＋ Nouvelle année scolaire…' },
            ]}
            disabled={annees.loading}
          />
          <FilterField
            id="cal-source"
            label="Source"
            value={filtres.source}
            onChange={(v) => majFiltre('source', v)}
            placeholder="Toutes (FWB et École)"
            options={optionsStatut(SOURCES_CALENDRIER)}
          />
          <FilterField
            id="cal-type"
            label="Type"
            value={filtres.type}
            onChange={(v) => majFiltre('type', v)}
            placeholder="Tous les types"
            options={optionsStatut(TYPES_CALENDRIER)}
          />
        </FilterBar>

        {annee && (
          <p style={{ margin: `0 0 ${ADMIN_SPACING.lg}`, fontSize: '13px', display: 'flex', flexWrap: 'wrap', gap: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.lg}`, alignItems: 'center' }}>
            {[1, 2].map((n) => {
              const p = annee.periodes?.find((x) => x.numero === n);
              return p ? (
                <span key={n}>
                  <PeriodeBadge numero={n} /> {formatPlage(p.date_debut, p.date_fin)}
                </span>
              ) : null;
            })}
            {annee.can?.update && annee.statut !== 'archivee' && (
              <Link to={lienModifierPeriodes(annee.id, { retour: `/admin/calendrier-scolaire?annee_scolaire_id=${annee.id}` })}>
                Modifier les dates des périodes
              </Link>
            )}
            <Link to="/admin/annees-scolaires">Gérer les années scolaires</Link>
          </p>
        )}

        {dernierImport && dernierImport.anneeId === annee?.id && !dernierImport.verifie && (
          <Banner tone="warning">
            <strong>Dates FWB à confirmer.</strong> Le fichier importé n'a pas encore été vérifié par la direction
            {dernierImport.source_url ? ` (source : ${dernierImport.source_url})` : ''}. Contrôlez-les avant de créer des classes.
          </Banner>
        )}

        {contenu}
      </AdminPageContent>

      {entreeModale && (
        <CalendrierEntreeModal
          annee={annee}
          entree={entreeModale.entree}
          onClose={() => setEntreeModale(null)}
          onDone={(message) => {
            setEntreeModale(null);
            toast.success(message);
            calendrier.reload();
          }}
        />
      )}

      {aSupprimer && (
        <AdminModal
          isOpen
          title={`Supprimer « ${aSupprimer.libelle} »${estSourceMasquable(aSupprimer.source) ? ' (FWB)' : ''} ?`}
          size="sm"
          onClose={() => setASupprimer(null)}
          footer={
            <>
              <AdminButton variant="secondary" onClick={() => setASupprimer(null)}>
                Conserver la date
              </AdminButton>
              <AdminButton variant="danger" onClick={supprimer} loading={envoi}>
                Supprimer « {aSupprimer.libelle} »
              </AdminButton>
            </>
          }
        >
          <strong>Conséquence</strong>
          <ul style={{ margin: `${ADMIN_SPACING.sm} 0 0`, paddingLeft: '20px' }}>
            <li>Cette date ne sera plus sautée pour les nouvelles classes.</li>
            <li>Les sessions déjà générées ne bougent pas.</li>
            {estSourceMasquable(aSupprimer.source) ? (
              <li>L'entrée FWB est masquée : cette suppression sera conservée lors des prochains imports FWB.</li>
            ) : (
              <li>L'entrée École est définitivement supprimée.</li>
            )}
          </ul>
        </AdminModal>
      )}
    </>
  );
}
