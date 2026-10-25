import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';
import AdminButton from '../components/AdminButton';
import AdminModal from '../components/AdminModal';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../components/ui/DataStates';
import LinkButton from '../components/ui/LinkButton';
import Banner from '../components/ui/Banner';
import CarteMois from '../components/timesheets/CarteMois';
import { grouperParSemaine, titreSemaine } from '../utils/semaines';
import SyntheseMois from '../components/timesheets/SyntheseMois';
import AjouterHeuresModal from '../components/timesheets/AjouterHeuresModal';
import EncoderSessionModal from '../components/timesheets/EncoderSessionModal';
import SoumettreMoisModal from '../components/timesheets/SoumettreMoisModal';
import MoisSoumisConfirmation from '../components/timesheets/MoisSoumisConfirmation';
import ReconfirmationMois from '../components/timesheets/ReconfirmationMois';
import MaSignatureModal from '../components/signature/MaSignatureModal';
import { creerSaisie, soumettreMois, supprimerSaisie, useMonMois } from '../hooks/useTimesheets';
import { useCours } from '../hooks/useCours';
import { useToast } from '../hooks/useToast';
import { getErrorMessage } from '../api/errors';
import { MOIS_LONGS, formatDateCourte } from '../utils/dates';
import { formatDuree, formatEuros, formatHeures } from '../utils/format';
import { libelleSession } from '../utils/classes';

const pad = (n) => String(n).padStart(2, '0');

/** Écran mensuel « Mon mois » du professeur (UI-M4, mobile-first) ; le staff passe par `/admin/timesheets`. */
export default function TimesheetsPage() {
  const { user } = useAuth();
  if (user.role !== 'professeur') return <Navigate to="/admin/timesheets" replace />;
  return <MonMois user={user} />;
}

function MonMois({ user }) {
  const toast = useToast();
  const navigate = useNavigate();
  const aujourdhui = new Date();
  const [periode, setPeriode] = useState({ annee: aujourdhui.getFullYear(), mois: aujourdhui.getMonth() + 1 });
  const mon = useMonMois(periode.annee, periode.mois);
  const cours = useCours();
  const [ajout, setAjout] = useState(false);
  const [edition, setEdition] = useState(null);
  const [autreDuree, setAutreDuree] = useState(null);
  const [suppression, setSuppression] = useState(null);
  const [soumission, setSoumission] = useState(false);
  const [confirmation, setConfirmation] = useState(false);
  const [signature, setSignature] = useState(false);
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);

  function aller(delta) {
    const d = new Date(periode.annee, periode.mois - 1 + delta, 1);
    setPeriode({ annee: d.getFullYear(), mois: d.getMonth() + 1 });
    setErreur(null);
  }

  async function agir(fn, succes) {
    setEnvoi(true);
    setErreur(null);
    try {
      await fn();
      if (succes) toast.success(succes);
      mon.reload();
      return true;
    } catch (err) {
      setErreur(getErrorMessage(err));
      return false;
    } finally {
      setEnvoi(false);
    }
  }

  // Encoder une séance = enregistrer ses heures défrayables en brouillon. Rien n'est soumis ici.
  const encoder = (session) =>
    agir(
      () => creerSaisie({ course_session_id: session.id, nombre_heures: Number(session.duree_par_defaut) }),
      `Encodé · ${formatDuree(session.duree_par_defaut)}. Ces heures partiront avec le mois entier.`,
    );

  // Soumission du mois entier : toutes les heures encodées du mois, sans sélection.
  async function soumettre() {
    const ok = await agir(() => soumettreMois({ annee: periode.annee, mois: periode.mois }));
    setSoumission(false);
    if (ok) setConfirmation(true);
  }

  const titreMois = `${MOIS_LONGS[periode.mois - 1][0].toUpperCase()}${MOIS_LONGS[periode.mois - 1].slice(1)} ${periode.annee}`;
  const data = mon.data;
  const vide = data && data.sessions.length === 0 && data.libres.length === 0;
  const semaines = data ? grouperParSemaine(data.sessions, data.libres) : [];
  const seances = (data?.sessions || [])
    .filter((s) => s.peut_encoder && s.encodage === 'a_encoder')
    .map((s) => ({ value: String(s.id), label: `${formatDateCourte(s.date)} — ${s.classe_libelle} · ${libelleSession(s)}` }));
  const coursOptions = (cours.data || []).map((c) => ({ value: String(c.id), label: c.titre }));

  return (
    <>
      <AdminPageHeader icon="📋" title="Mon mois" description="Encodez vos heures au fil du mois ; vous les soumettez une seule fois, quand le mois est complet." />
      <AdminPageContent>
        <div className="mm">
          <nav aria-label="Choix du mois" className="mm-mois">
            <button type="button" className="mm-mois__fleche" onClick={() => aller(-1)} aria-label="Mois précédent">
              ‹
            </button>
            <strong>{titreMois}</strong>
            <button type="button" className="mm-mois__fleche" onClick={() => aller(1)} aria-label="Mois suivant">
              ›
            </button>
          </nav>

          {erreur && (
            <Banner tone="error" role="alert">
              <strong>{erreur}</strong>
            </Banner>
          )}
          {data?.mois_soumis && (
            <Banner tone="info" role="status">
              <strong>Ce mois est soumis à la direction.</strong> Vos heures ne sont plus modifiables. Une correction passe par la direction (remise en brouillon du mois).
            </Banner>
          )}
          {data?.employeur && (
            <p className="mm-employeur" data-testid="employeur-du-mois">
              Employeur ce mois : <strong>{data.employeur.nom}</strong>
              <span>Pour toute question sur votre contrat ou vos données, contactez la direction.</span>
            </p>
          )}
          <ReconfirmationMois key={`${periode.annee}-${periode.mois}`} professeurId={user.professeur?.id} annee={periode.annee} mois={periode.mois} onChange={mon.reload} />
          {mon.loading && !data && <LoadingBlock message="Chargement de votre mois…" lignes={4} />}
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
              Aucune séance n'est prévue ce mois-ci et vous n'avez encodé aucune heure. Vous pouvez tout de même ajouter des heures (préparation, réunion…) à la date de votre choix.
            </EmptyBlock>
          )}

          {data && !vide && (
            <>
              <SyntheseMois synthese={data.synthese} />

              <div className="mm-tete">
                <h2>Séances et heures du mois</h2>
                <AdminButton size="sm" variant="secondary" onClick={() => setAjout(true)} disabled={data.mois_soumis}>
                  + Ajouter des heures
                </AdminButton>
              </div>

              {semaines.map((semaine) => (
                <section key={semaine.lundi} aria-label={titreSemaine(semaine.lundi)}>
                  <h3 className="mm-semaine">{titreSemaine(semaine.lundi)}</h3>
                  <ul className="mm-liste">
                    {semaine.cartes.map((c) => (
                      <CarteMois
                        key={c.cle}
                        carte={c}
                        verrouille={data.mois_soumis}
                        occupe={envoi}
                        onEncoder={encoder}
                        onAutreDuree={setAutreDuree}
                        onModifier={setEdition}
                        onSupprimer={setSuppression}
                      />
                    ))}
                  </ul>
                </section>
              ))}

              <div className="sticky-actions">
                <div className="sticky-actions__total only-mobile" aria-live="polite">
                  <span>Total du mois</span>
                  <strong>
                    {formatHeures(data.synthese.heures)} · {formatEuros(data.synthese.montant)}
                  </strong>
                </div>
                <div className="sticky-actions__boutons">
                  <AdminButton onClick={() => setSoumission(true)} disabled={data.mois_soumis || data.synthese.nb_brouillons === 0 || envoi}>
                    {data.mois_soumis ? 'Mois soumis' : 'Soumettre'}
                    <span className="hide-mobile">{data.mois_soumis ? '' : ' le mois'}</span>
                  </AdminButton>
                </div>
              </div>
            </>
          )}
        </div>
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
      {autreDuree && (
        <EncoderSessionModal
          session={autreDuree}
          classeLibelle={autreDuree.classe_libelle}
          onClose={() => setAutreDuree(null)}
          onDone={(m) => {
            setAutreDuree(null);
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
      {soumission && data && (
        <SoumettreMoisModal
          data={data}
          titreMois={titreMois}
          envoi={envoi}
          onConfirmer={soumettre}
          onCreerSignature={() => setSignature(true)}
          onClose={() => setSoumission(false)}
        />
      )}
      {signature && <MaSignatureModal nom={user?.name} onClose={() => setSignature(false)} />}
      {confirmation && <MoisSoumisConfirmation titreMois={titreMois} onAccueil={() => navigate('/aujourdhui')} onFermer={() => setConfirmation(false)} />}
    </>
  );
}
