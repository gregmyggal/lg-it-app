import { useEffect, useMemo, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';
import AdminButton from '../components/AdminButton';
import AdminModal from '../components/AdminModal';
import { Section } from '../components/ui/Card';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../components/ui/DataStates';
import LinkButton from '../components/ui/LinkButton';
import Banner from '../components/ui/Banner';
import { Table, Th, Td, Tr } from '../components/ui/Table';
import StatutBadge from '../components/ui/StatutBadge';
import SessionsDuMoisTable from '../components/timesheets/SessionsDuMoisTable';
import SyntheseMois from '../components/timesheets/SyntheseMois';
import AjouterHeuresModal from '../components/timesheets/AjouterHeuresModal';
import ReconfirmationMois from '../components/timesheets/ReconfirmationMois';
import { creerSaisie, soumettreMois, supprimerSaisie, useMonMois } from '../hooks/useTimesheets';
import { useCours } from '../hooks/useCours';
import { useToast } from '../hooks/useToast';
import { getErrorMessage } from '../api/errors';
import { STATUTS_TIMESHEET, TYPES_ACTIVITE, getStatut } from '../utils/statuts';
import { MOIS_LONGS, formatDateCourte } from '../utils/dates';
import { formatEuros, formatHeures } from '../utils/format';

const pad = (n) => String(n).padStart(2, '0');

/** Écran mensuel « Encoder mon mois » du professeur (mock-up 02) ; le staff passe par `/admin/timesheets`. */
export default function TimesheetsPage() {
  const { user } = useAuth();
  if (user.role !== 'professeur') return <Navigate to="/admin/timesheets" replace />;
  return <MonMois user={user} />;
}

function MonMois({ user }) {
  const toast = useToast();
  const aujourdhui = new Date();
  const [periode, setPeriode] = useState({ annee: aujourdhui.getFullYear(), mois: aujourdhui.getMonth() + 1 });
  const mon = useMonMois(periode.annee, periode.mois);
  const cours = useCours();
  const [locales, setLocales] = useState({});
  const [ajout, setAjout] = useState(false);
  const [edition, setEdition] = useState(null);
  const [suppression, setSuppression] = useState(null);
  const [soumission, setSoumission] = useState(false);
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);

  // Sessions à encoder : préremplies (durée de la session), incluses par défaut.
  useEffect(() => {
    if (!mon.data) return;
    setLocales(
      Object.fromEntries(
        mon.data.sessions
          .filter((s) => s.encodage === 'a_encoder' && s.peut_encoder)
          .map((s) => [s.id, { inclure: true, heures: String(s.duree_par_defaut) }]),
      ),
    );
  }, [mon.data]);

  const incluses = useMemo(
    () => Object.entries(locales).filter(([, l]) => l.inclure && Number(l.heures) >= 0.5).map(([id, l]) => ({ course_session_id: Number(id), nombre_heures: Number(l.heures) })),
    [locales],
  );
  const heuresEnAttente = incluses.reduce((t, s) => t + s.nombre_heures, 0);

  function aller(delta) {
    const d = new Date(periode.annee, periode.mois - 1 + delta, 1);
    setPeriode({ annee: d.getFullYear(), mois: d.getMonth() + 1 });
    setErreur(null);
  }

  function modifierLocale(id, patch) {
    setLocales((prev) => ({ ...prev, [id]: { ...prev[id], ...patch } }));
  }

  async function agir(fn, succes) {
    setEnvoi(true);
    setErreur(null);
    try {
      await fn();
      toast.success(succes);
      mon.reload();
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  const enregistrer = () =>
    agir(async () => {
      for (const s of incluses) await creerSaisie(s);
    }, 'Brouillon enregistré. Vous pourrez encore modifier ces heures avant de soumettre le mois.');

  async function soumettre() {
    await agir(() => soumettreMois({ annee: periode.annee, mois: periode.mois, sessions: incluses }), 'Mois soumis. La direction peut maintenant valider vos heures.');
    setSoumission(false);
  }

  const titreMois = `${MOIS_LONGS[periode.mois - 1][0].toUpperCase()}${MOIS_LONGS[periode.mois - 1].slice(1)} ${periode.annee}`;
  const data = mon.data;
  const vide = data && data.sessions.length === 0 && data.libres.length === 0;
  const seances = (data?.sessions || [])
    .filter((s) => s.peut_encoder && s.encodage === 'a_encoder')
    .map((s) => ({ value: String(s.id), label: `${formatDateCourte(s.date)} — ${s.classe_libelle} · ${s.libelle}` }));
  const coursOptions = (cours.data || []).map((c) => ({ value: String(c.id), label: c.titre }));

  return (
    <>
      <AdminPageHeader
        icon="📋"
        title="Encoder mon mois"
        description="Vos sessions sont préremplies ; ajoutez vos autres heures (préparation, etc.), vérifiez le total, puis soumettez le mois."
      />
      <AdminPageContent>
        <nav aria-label="Choix du mois" style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '16px', flexWrap: 'wrap' }}>
          <AdminButton size="sm" variant="secondary" onClick={() => aller(-1)}>
            ‹ Mois précédent
          </AdminButton>
          <strong style={{ minWidth: '140px', textAlign: 'center' }}>{titreMois}</strong>
          <AdminButton size="sm" variant="secondary" onClick={() => aller(1)}>
            Mois suivant ›
          </AdminButton>
        </nav>

        {erreur && (
          <Banner tone="error" role="alert">
            <strong>{erreur}</strong>
          </Banner>
        )}
        <ReconfirmationMois key={`${periode.annee}-${periode.mois}`} professeurId={user.professeur?.id} annee={periode.annee} mois={periode.mois} onChange={mon.reload} />
        {mon.loading && <LoadingBlock message="Chargement de votre mois…" lignes={4} />}
        {mon.error && <ErrorBlock message="Impossible de charger votre mois. Vos saisies ne sont pas perdues. Vérifiez votre connexion puis réessayez." onRetry={mon.reload} />}

        {vide && (
          <EmptyBlock
            icon="🗓️"
            title="Rien à encoder pour ce mois"
            actions={
              <>
                <AdminButton onClick={() => setAjout(true)}>Ajouter des heures</AdminButton>
                <LinkButton to="/mes-classes">Aller à Mes classes</LinkButton>
              </>
            }
          >
            Aucune de vos sessions n'a encore commencé ce mois-ci et vous n'avez encodé aucune heure. Vous pouvez tout de même ajouter des heures (préparation, réunion…) à la date de votre choix.
          </EmptyBlock>
        )}

        {data && !vide && (
          <>
            <Section
              title="1. Mes sessions du mois"
              subtitle="Heures défrayées préremplies (séance + préparation), modifiables. Seules les sessions commencées sont proposées. Décochez une session pour la laisser « À encoder » plus tard."
              bodyPadding={false}
            >
              {data.sessions.length === 0 ? (
                <p style={{ padding: '16px', margin: 0 }}>Aucune session commencée ce mois-ci.</p>
              ) : (
                <SessionsDuMoisTable
                  sessions={data.sessions}
                  saisiesLocales={locales}
                  onChange={modifierLocale}
                  onModifier={setEdition}
                  onSupprimer={setSuppression}
                />
              )}
            </Section>

            <Section
              title="2. Mes autres heures du mois"
              subtitle="Préparation, animation ou autre travail, avec ou sans cours : à la date où vous l'avez réalisé."
              bodyPadding={false}
              actions={
                <AdminButton size="sm" onClick={() => setAjout(true)}>
                  Ajouter des heures
                </AdminButton>
              }
            >
              {data.libres.length === 0 ? (
                <p style={{ padding: '16px', margin: 0 }}>Aucune autre heure encodée ce mois-ci.</p>
              ) : (
                <Table caption="Mes autres heures du mois" minWidth="640px">
                  <thead>
                    <tr>
                      <Th>Date</Th>
                      <Th>Activité</Th>
                      <Th>Cours</Th>
                      <Th>Durée</Th>
                      <Th>Montant</Th>
                      <Th>État</Th>
                      <Th>Actions</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.libres.map((x) => (
                      <Tr key={x.id}>
                        <Td>{formatDateCourte(x.date_prestation.slice(0, 10))}</Td>
                        <Td>{getStatut(TYPES_ACTIVITE, x.type_activite).label}</Td>
                        <Td>{x.cours?.titre || '—'}</Td>
                        <Td>{formatHeures(x.nombre_heures)}</Td>
                        <Td>{formatEuros(x.montant_brut)}</Td>
                        <Td>
                          <StatutBadge table={STATUTS_TIMESHEET} valeur={x.statut_validation} />
                        </Td>
                        <Td>
                          <div style={{ display: 'flex', gap: '6px' }}>
                            {x.can?.update && (
                              <AdminButton size="sm" variant="secondary" onClick={() => setEdition(x)}>
                                Modifier
                              </AdminButton>
                            )}
                            {x.can?.delete && (
                              <AdminButton size="sm" variant="secondary" onClick={() => setSuppression(x)}>
                                Supprimer
                              </AdminButton>
                            )}
                          </div>
                        </Td>
                      </Tr>
                    ))}
                  </tbody>
                </Table>
              )}
            </Section>

            <Section title="3. Total du mois" subtitle="Brouillons et saisies à enregistrer comprises.">
              <SyntheseMois synthese={data.synthese} heuresEnAttente={heuresEnAttente} />
              <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap', marginTop: '16px' }}>
                <AdminButton variant="secondary" onClick={enregistrer} disabled={incluses.length === 0 || envoi}>
                  Enregistrer en brouillon
                </AdminButton>
                <AdminButton onClick={() => setSoumission(true)} disabled={(incluses.length === 0 && data.synthese.nb_brouillons === 0) || envoi}>
                  Soumettre le mois
                </AdminButton>
              </div>
            </Section>

          </>
        )}
      </AdminPageContent>

      {ajout && (
        <AjouterHeuresModal
          dateParDefaut={`${periode.annee}-${pad(periode.mois)}-01`}
          cours={coursOptions}
          seances={seances}
          onClose={() => setAjout(false)}
          onDone={(m) => {
            setAjout(false);
            toast.success(m);
            mon.reload();
          }}
        />
      )}
      {edition && (
        <AjouterHeuresModal
          saisie={edition}
          dateParDefaut=""
          onClose={() => setEdition(null)}
          onDone={(m) => {
            setEdition(null);
            toast.success(m);
            mon.reload();
          }}
        />
      )}
      {suppression && (
        <AdminModal
          isOpen
          title="Supprimer ces heures ?"
          size="sm"
          onClose={() => setSuppression(null)}
          footer={
            <>
              <AdminButton variant="secondary" onClick={() => setSuppression(null)}>
                Conserver
              </AdminButton>
              <AdminButton
                variant="danger"
                loading={envoi}
                onClick={async () => {
                  await agir(() => supprimerSaisie(suppression.id), 'Heures supprimées.');
                  setSuppression(null);
                }}
              >
                Supprimer ces heures
              </AdminButton>
            </>
          }
        >
          <p style={{ margin: 0 }}>
            {formatHeures(suppression.nombre_heures)} du {formatDateCourte(suppression.date_prestation.slice(0, 10))} seront supprimées. Seuls les brouillons peuvent l'être.
          </p>
        </AdminModal>
      )}
      {soumission && (
        <AdminModal
          isOpen
          title="Soumettre mon mois"
          size="sm"
          onClose={() => setSoumission(false)}
          footer={
            <>
              <AdminButton variant="secondary" onClick={() => setSoumission(false)}>
                Annuler
              </AdminButton>
              <AdminButton loading={envoi} onClick={soumettre}>
                Soumettre les heures
              </AdminButton>
            </>
          }
        >
          <p style={{ margin: 0 }}>
            Une fois soumises, ces heures sont visibles par la direction et ne sont plus modifiables par vous. Elles seront ensuite validées par la direction.
          </p>
        </AdminModal>
      )}
    </>
  );
}
