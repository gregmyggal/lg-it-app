import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import AdminModal from '../../components/AdminModal';
import Banner from '../../components/ui/Banner';
import LinkButton from '../../components/ui/LinkButton';
import StatutBadge from '../../components/ui/StatutBadge';
import PeriodeBadge from '../../components/classes/PeriodeBadge';
import { Table, Th, Td, Tr } from '../../components/ui/Table';
import { FilterBar, FilterField } from '../../components/ui/Filters';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../../components/ui/DataStates';
import {
  activerAnnee,
  archiverAnnee,
  importerCalendrierFwb,
  reactiverAnnee,
  supprimerAnnee,
  useAnneesScolaires,
} from '../../hooks/useAnneesScolaires';
import { useToast } from '../../hooks/useToast';
import { getErrorData, getErrorMessage, getStatus } from '../../api/errors';
import { STATUTS_ANNEE } from '../../utils/statuts';
import { lienModifierPeriodes, lienNouvelleAnnee } from '../../utils/annees';
import { aujourdhuiISO, formatDate, formatDateHeure } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES } from '../../styles/AdminDesignSystem';

const FILTRES = [
  { value: 'actives', label: 'Actives et brouillons' },
  { value: 'toutes', label: 'Toutes (archivées incluses)' },
  { value: 'archivees', label: 'Archivées' },
];

const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

/** Écran « Années scolaires » : liste, statut, périodes, calendrier, archivage, suppression (mock-up CLS-03/01). */
export default function AnneesScolairesPage() {
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const annees = useAnneesScolaires();
  const filtre = FILTRES.some((f) => f.value === params.get('afficher')) ? params.get('afficher') : 'actives';
  const [modale, setModale] = useState(null); // { type: 'archiver'|'supprimer', annee }
  const [refus, setRefus] = useState(null); // message de refus de suppression (409)
  const [envoi, setEnvoi] = useState(false);
  const [succes, setSucces] = useState(null); // { message, anneeId } : bandeau « Annuler l'archivage »
  const [importEnCours, setImport] = useState(null); // id de l'année en cours d'import

  const toutes = useMemo(() => annees.data || [], [annees.data]);
  const visibles = useMemo(
    () =>
      toutes.filter((a) => (filtre === 'toutes' ? true : filtre === 'archivees' ? a.statut === 'archivee' : a.statut !== 'archivee')),
    [toutes, filtre],
  );
  const aujourdhui = aujourdhuiISO();
  const sansCalendrier = toutes.filter((a) => a.statut !== 'archivee' && a.calendrier_count === 0 && a.can?.import_fwb);

  function changerFiltre(valeur) {
    setParams(valeur === 'actives' ? new URLSearchParams() : new URLSearchParams({ afficher: valeur }), { replace: true });
  }

  async function importer(annee) {
    setImport(annee.id);
    try {
      const resultat = await importerCalendrierFwb(annee.id);
      toast.success(resultat.message || `Calendrier FWB ${annee.libelle} importé.`);
      annees.reload();
    } catch (err) {
      toast.error(getErrorMessage(err, "L'import du calendrier FWB a échoué."));
    } finally {
      setImport(null);
    }
  }

  async function archiver(annee) {
    setEnvoi(true);
    try {
      await archiverAnnee(annee.id);
      setModale(null);
      setSucces({
        anneeId: annee.id,
        message: `Année ${annee.libelle} archivée. Ses ${pluriel(annee.classes_count ?? 0, 'classe')}, séances et heures encodées sont inchangées.`,
      });
      annees.reload();
    } catch (err) {
      setModale(null);
      toast.error(getErrorMessage(err, "L'année n'a pas pu être archivée."));
    } finally {
      setEnvoi(false);
    }
  }

  async function reactiver(annee, { annulation = false } = {}) {
    setEnvoi(true);
    try {
      await reactiverAnnee(annee.id);
      setSucces(null);
      toast.success(annulation ? `Archivage de ${annee.libelle} annulé : l'année est de nouveau active.` : `Année ${annee.libelle} réactivée.`);
      annees.reload();
    } catch (err) {
      toast.error(getErrorMessage(err, "L'année n'a pas pu être réactivée."));
    } finally {
      setEnvoi(false);
    }
  }

  async function activer(annee) {
    setEnvoi(true);
    try {
      await activerAnnee(annee.id);
      toast.success(`Année ${annee.libelle} activée : elle est maintenant proposée à la création de classes.`);
      annees.reload();
    } catch (err) {
      toast.error(getErrorMessage(err, "L'année n'a pas pu être activée."));
    } finally {
      setEnvoi(false);
    }
  }

  async function supprimer(annee) {
    setEnvoi(true);
    setRefus(null);
    try {
      await supprimerAnnee(annee.id);
      setModale(null);
      toast.success(`Année ${annee.libelle} supprimée.`);
      annees.reload();
    } catch (err) {
      if (getStatus(err) === 409) {
        setRefus(getErrorData(err).message || getErrorMessage(err));
      } else {
        setModale(null);
        toast.error(getErrorMessage(err, "L'année n'a pas pu être supprimée."));
      }
    } finally {
      setEnvoi(false);
    }
  }

  let contenu;
  if (annees.loading) {
    contenu = <LoadingBlock message="Chargement des années scolaires…" />;
  } else if (annees.error) {
    contenu = (
      <ErrorBlock
        message="Impossible de charger les années scolaires. Vérifiez votre connexion puis réessayez."
        onRetry={annees.reload}
      />
    );
  } else if (toutes.length === 0) {
    contenu = (
      <EmptyBlock
        icon="🗓️"
        title="Aucune année scolaire"
        actions={
          <LinkButton to={lienNouvelleAnnee()} variant="primary">
            Créer une année scolaire
          </LinkButton>
        }
      >
        Créez votre première année : vous indiquez les dates des deux périodes (nous vous les proposons), puis vous pouvez
        importer le calendrier FWB et ouvrir des classes.
      </EmptyBlock>
    );
  } else if (visibles.length === 0) {
    contenu = (
      <EmptyBlock
        icon="🔎"
        title="Aucune année pour ce filtre"
        actions={
          <AdminButton variant="secondary" onClick={() => changerFiltre('toutes')}>
            Afficher toutes les années
          </AdminButton>
        }
      >
        Aucune année scolaire ne correspond à « {FILTRES.find((f) => f.value === filtre).label} ».
      </EmptyBlock>
    );
  } else {
    contenu = (
      <>
        <h2 style={{ margin: `0 0 ${ADMIN_SPACING.xs}`, fontSize: '18px' }}>Années scolaires</h2>
        <p style={{ margin: `0 0 ${ADMIN_SPACING.md}`, fontSize: '13px', color: ADMIN_COLORS.textSecondary }} aria-live="polite">
          {pluriel(visibles.length, 'année')} · la plus récente en premier
        </p>
        <Table caption="Années scolaires, leurs périodes et leur statut" minWidth="1080px">
          <thead>
            <tr>
              <Th>Année</Th>
              <Th>Statut</Th>
              <Th>P1 · Période 1</Th>
              <Th>P2 · Période 2</Th>
              <Th>Classes</Th>
              <Th>Calendrier</Th>
              <Th>Dernière modif.</Th>
              <Th>Actions</Th>
            </tr>
          </thead>
          <tbody>
            {visibles.map((a) => {
              const archivee = a.statut === 'archivee';
              const p = (n) => a.periodes?.find((x) => x.numero === n);
              const enCours = (n) => p(n) && p(n).date_debut <= aujourdhui && aujourdhui <= p(n).date_fin;
              return (
                <Tr key={a.id}>
                  <Td>
                    <strong>{a.libelle}</strong>{' '}
                    {a.en_cours && <StatutBadge label="en cours" tone="primary" />}
                  </Td>
                  <Td>
                    <StatutBadge table={STATUTS_ANNEE} valeur={a.statut} />
                  </Td>
                  {[1, 2].map((n) => (
                    <Td key={n} style={{ whiteSpace: 'nowrap' }}>
                      <PeriodeBadge numero={n} /> {p(n) ? `${formatDate(p(n).date_debut)} → ${formatDate(p(n).date_fin)}` : '—'}
                      {enCours(n) && (
                        <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>P{n} en cours</div>
                      )}
                    </Td>
                  ))}
                  <Td>{a.classes_count ?? 0}</Td>
                  <Td>
                    {a.calendrier_count === 0 ? (
                      <>
                        <StatutBadge label="⚠ Vide" tone="warning" />
                        {!archivee && a.can?.import_fwb && (
                          <div style={{ marginTop: ADMIN_SPACING.xs }}>
                            <AdminButton
                              size="sm"
                              variant="secondary"
                              loading={importEnCours === a.id}
                              onClick={() => importer(a)}
                              aria-label={`Importer le calendrier FWB ${a.libelle}`}
                            >
                              Importer FWB
                            </AdminButton>
                          </div>
                        )}
                      </>
                    ) : (
                      `${a.calendrier_count} date${a.calendrier_count > 1 ? 's' : ''}`
                    )}
                  </Td>
                  <Td style={{ fontSize: '13px' }}>
                    {a.updated_at ? (
                      <>
                        {a.updated_by?.name ? `${a.updated_by.name} · ` : ''}
                        {formatDateHeure(a.updated_at).slice(0, 10)}
                      </>
                    ) : (
                      '—'
                    )}
                  </Td>
                  <Td>
                    <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap', alignItems: 'center' }}>
                      {archivee ? (
                        <span style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>Dates verrouillées</span>
                      ) : (
                        a.can?.update && (
                          <LinkButton to={lienModifierPeriodes(a.id)} variant="primary" size="sm">
                            Modifier les périodes<span className="sr-only"> {a.libelle}</span>
                          </LinkButton>
                        )
                      )}
                      {a.statut === 'active' && (
                        <LinkButton to={`/admin/calendrier-scolaire?annee_scolaire_id=${a.id}`} size="sm">
                          Calendrier<span className="sr-only"> {a.libelle}</span>
                        </LinkButton>
                      )}
                      {a.statut === 'active' && a.can?.archiver && (
                        <AdminButton size="sm" variant="secondary" onClick={() => setModale({ type: 'archiver', annee: a })}>
                          Archiver<span className="sr-only"> {a.libelle}</span>
                        </AdminButton>
                      )}
                      {a.statut === 'brouillon' && a.can?.update && (
                        <AdminButton size="sm" variant="secondary" onClick={() => activer(a)} disabled={envoi}>
                          Activer<span className="sr-only"> {a.libelle}</span>
                        </AdminButton>
                      )}
                      {archivee && a.can?.archiver && (
                        <AdminButton size="sm" variant="secondary" onClick={() => reactiver(a)} disabled={envoi}>
                          Réactiver<span className="sr-only"> {a.libelle}</span>
                        </AdminButton>
                      )}
                      <AdminButton
                        size="sm"
                        variant="secondary"
                        onClick={() => {
                          setRefus(null);
                          setModale({ type: 'supprimer', annee: a });
                        }}
                        aria-disabled={a.can?.delete === false ? 'true' : undefined}
                        style={{ color: ADMIN_TONES.error.fg, opacity: a.can?.delete === false ? 0.6 : 1 }}
                      >
                        Supprimer<span className="sr-only"> {a.libelle}</span>
                      </AdminButton>
                    </div>
                  </Td>
                </Tr>
              );
            })}
          </tbody>
        </Table>
      </>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="🗓️"
        title="Années scolaires"
        breadcrumb={<>Scolarité › Années scolaires</>}
        description="Gérez les années, leurs deux périodes, leur statut et leur calendrier."
        action={
          <>
            <LinkButton to="/admin/calendrier-scolaire">Calendrier scolaire</LinkButton>
            <LinkButton to={lienNouvelleAnnee()} variant="primary">
              ＋ Nouvelle année scolaire
            </LinkButton>
          </>
        }
      />
      <AdminPageContent>
        <Banner tone="info">
          Les deux périodes d'une année fixent les dates entre lesquelles vos classes démarrent leurs séances.{' '}
          <strong>Modifier une période ne déplace et ne supprime jamais de séance</strong> : l'impact est affiché avant
          d'enregistrer.
        </Banner>

        {succes && (
          <Banner
            tone="success"
            actions={
              <AdminButton
                size="sm"
                variant="secondary"
                disabled={envoi}
                onClick={() => reactiver(toutes.find((a) => a.id === succes.anneeId) || { id: succes.anneeId, libelle: '' }, { annulation: true })}
              >
                Annuler l'archivage
              </AdminButton>
            }
          >
            <strong>{succes.message}</strong>
          </Banner>
        )}

        {sansCalendrier.length > 0 &&
          sansCalendrier.map((a) => (
            <Banner
              key={a.id}
              tone="warning"
              actions={
                <AdminButton size="sm" onClick={() => importer(a)} loading={importEnCours === a.id}>
                  Importer le calendrier FWB {a.libelle}
                </AdminButton>
              }
            >
              <strong>Le calendrier scolaire {a.libelle} est vide.</strong> Sans vacances ni jours fériés, les séances d'une
              classe de cette année ne pourront pas être générées.
            </Banner>
          ))}

        <FilterBar label="Filtre des années scolaires">
          <FilterField id="annees-afficher" label="Afficher" value={filtre} onChange={changerFiltre} options={FILTRES} />
        </FilterBar>

        {contenu}
      </AdminPageContent>

      {modale?.type === 'archiver' && (
        <AdminModal
          isOpen
          size="md"
          title={`Archiver l'année ${modale.annee.libelle} ?`}
          onClose={() => setModale(null)}
          footer={
            <>
              <AdminButton variant="secondary" onClick={() => setModale(null)}>
                Annuler
              </AdminButton>
              <AdminButton onClick={() => archiver(modale.annee)} loading={envoi}>
                Archiver {modale.annee.libelle}
              </AdminButton>
            </>
          }
        >
          {modale.annee.en_cours && (
            <Banner tone="warning">
              <strong>Cette année est en cours.</strong> {pluriel(modale.annee.classes_count ?? 0, 'classe')}.
            </Banner>
          )}
          <strong>Ce qui change</strong>
          <ul style={{ margin: `${ADMIN_SPACING.sm} 0 ${ADMIN_SPACING.lg}`, paddingLeft: '20px' }}>
            <li>Elle n'est plus proposée à la création d'une classe ni dans les alertes « P2 à planifier ».</li>
            <li>Ses dates de périodes sont verrouillées (réactivez pour les modifier).</li>
            <li>Elle reste consultable avec le filtre « Archivées ».</li>
          </ul>
          <strong>Ce qui ne change pas</strong>
          <ul style={{ margin: `${ADMIN_SPACING.sm} 0 0`, paddingLeft: '20px' }}>
            <li>Classes, séances et calendrier de l'année.</li>
            <li>Les professeurs continuent d'encoder leurs heures (timesheets indépendantes).</li>
          </ul>
        </AdminModal>
      )}

      {modale?.type === 'supprimer' && <ModaleSuppression modale={modale} refus={refus} envoi={envoi} onClose={() => setModale(null)} onSupprimer={supprimer} onArchiver={(a) => { setModale({ type: 'archiver', annee: a }); }} />}
    </>
  );
}

/** Suppression : refusée (avec classes, explication + « Archiver à la place ») ou confirmée (année vide, irréversible). */
function ModaleSuppression({ modale, refus, envoi, onClose, onSupprimer, onArchiver }) {
  const a = modale.annee;
  const impossible = a.can?.delete === false || Boolean(refus);
  if (impossible) {
    const message = refus || a.can?.raison_non_supprimable || `L'année ${a.libelle} contient des classes.`;
    return (
      <AdminModal
        isOpen
        size="md"
        title="Suppression impossible"
        onClose={onClose}
        footer={
          <>
            <AdminButton variant="secondary" onClick={onClose}>
              Fermer
            </AdminButton>
            {a.statut === 'active' && a.can?.archiver && <AdminButton onClick={() => onArchiver(a)}>Archiver l'année à la place</AdminButton>}
          </>
        }
      >
        <p style={{ marginTop: 0 }}>
          <strong>L'année {a.libelle} : {message}</strong>
        </p>
        <p style={{ marginBottom: 0 }}>
          Une année qui porte des classes ne peut pas être supprimée : cela effacerait leur historique et leurs heures.
          Archivez-la à la place : elle disparaît des listes de travail mais reste consultable.
        </p>
      </AdminModal>
    );
  }
  return (
    <AdminModal
      isOpen
      size="md"
      title={`Supprimer l'année ${a.libelle} ?`}
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton variant="danger" onClick={() => onSupprimer(a)} loading={envoi}>
            Supprimer définitivement
          </AdminButton>
        </>
      }
    >
      <p style={{ marginTop: 0 }}>Cette année ne contient aucune classe. Seront supprimés définitivement :</p>
      <ul style={{ margin: `0 0 ${ADMIN_SPACING.lg}`, paddingLeft: '20px' }}>
        <li>
          ses 2 périodes
          {a.date_debut && a.date_fin ? ` (${formatDate(a.date_debut)} → ${formatDate(a.date_fin)})` : ''} ;
        </li>
        <li>{pluriel(a.calendrier_count ?? 0, 'date')} de calendrier scolaire.</li>
      </ul>
      <Banner tone="warning" style={{ marginBottom: 0 }}>
        <strong>Action irréversible.</strong> En cas de doute, archivez plutôt.
      </Banner>
    </AdminModal>
  );
}
