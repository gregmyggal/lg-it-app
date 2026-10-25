import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import AujourdhuiDirectionPage from './AujourdhuiDirectionPage';
import Banner from '../components/ui/Banner';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../components/ui/DataStates';
import { creerSaisie, supprimerSaisie } from '../hooks/useTimesheets';
import { useMonAccueil } from '../hooks/useMonAccueil';
import { getErrorMessage } from '../api/errors';
import { formatDateCourte, formatHoraire, MOIS_LONGS } from '../utils/dates';
import { formatDuree, formatEuros } from '../utils/format';

const DELAI_ANNULATION_MS = 5000;
const SORTIE_MS = 300;

/** Anneau de progression du mois : séances encodées / séances du mois. */
function Anneau({ encodees, total }) {
  const circonference = 2 * Math.PI * 42;
  const part = total > 0 ? Math.min(encodees / total, 1) : 0;
  return (
    <div className="auj-anneau" role="img" aria-label={`${encodees} séances encodées sur ${total}`}>
      <svg viewBox="0 0 100 100" aria-hidden="true">
        <circle className="auj-anneau__fond" cx="50" cy="50" r="42" />
        <circle className="auj-anneau__val" cx="50" cy="50" r="42" strokeDasharray={circonference} strokeDashoffset={circonference * (1 - part)} />
      </svg>
      <b>{total > 0 ? `${encodees}/${total}` : '–'}</b>
    </div>
  );
}

function titreDuJour(iso) {
  const [annee, mois, jour] = iso.split('-').map(Number);
  const d = new Date(annee, mois - 1, jour);
  const nomJour = d.toLocaleDateString('fr-BE', { weekday: 'long' });
  return `${nomJour.charAt(0).toUpperCase()}${nomJour.slice(1)} ${jour} ${MOIS_LONGS[mois - 1]}`;
}

/**
 * Page d'arrivée du professeur (UI-M3) : prochaine séance, séances à encoder en 1 tap (annulable 5 s), progression du mois.
 * Le serveur reste la source de vérité : un encodage refusé (mois soumis, doublon…) affiche son message et la carte reste.
 */
export default function AujourdhuiPage() {
  const { user } = useAuth();
  if (user?.role !== 'professeur') return <AujourdhuiDirectionPage />;
  return <AujourdhuiProfesseur user={user} />;
}

function AujourdhuiProfesseur({ user }) {
  const accueil = useMonAccueil();
  const [enCours, setEnCours] = useState(null);
  const [sorties, setSorties] = useState([]);
  const [erreur, setErreur] = useState(null);
  const [annulable, setAnnulable] = useState(null);
  const minuteur = useRef(null);

  useEffect(() => () => clearTimeout(minuteur.current), []);

  const prenom = user?.professeur?.prenom || user?.name?.split(' ')[0] || '';
  const data = accueil.data;

  async function encoder(seance) {
    setEnCours(seance.id);
    setErreur(null);
    try {
      const saisie = await creerSaisie({ course_session_id: seance.id, nombre_heures: Number(seance.duree_par_defaut) });
      setSorties((prev) => [...prev, seance.id]);
      navigator.vibrate?.(10);
      clearTimeout(minuteur.current);
      setAnnulable({ saisieId: saisie.data?.id ?? saisie.id, heures: seance.duree_par_defaut });
      minuteur.current = setTimeout(() => setAnnulable(null), DELAI_ANNULATION_MS);
      setTimeout(() => {
        accueil.reload();
        setSorties((prev) => prev.filter((id) => id !== seance.id));
      }, SORTIE_MS);
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnCours(null);
    }
  }

  async function annuler() {
    const { saisieId } = annulable;
    clearTimeout(minuteur.current);
    setAnnulable(null);
    try {
      await supprimerSaisie(saisieId);
    } catch (err) {
      setErreur(getErrorMessage(err));
    }
    accueil.reload();
  }

  return (
    <div className="auj">
      {accueil.loading && !data && <LoadingBlock message="Chargement de votre journée…" lignes={4} />}
      {accueil.error && !data && (
        <ErrorBlock message="Impossible de charger votre journée. Vos saisies ne sont pas perdues. Vérifiez votre connexion puis réessayez." onRetry={accueil.reload} />
      )}

      {data && (
        <>
          <header className="auj-salut">
            <Anneau encodees={data.progression.seances_encodees} total={data.progression.seances_total} />
            <div>
              <small>{titreDuJour(data.date)}</small>
              <h1>Bonjour {prenom}</h1>
              <small>
                {data.progression.seances_encodees} séance{data.progression.seances_encodees > 1 ? 's' : ''} encodée{data.progression.seances_encodees > 1 ? 's' : ''} ·{' '}
                {formatEuros(data.progression.montant)}
              </small>
            </div>
          </header>

          {erreur && (
            <Banner tone="error" role="alert">
              <strong>{erreur}</strong>
            </Banner>
          )}

          {data.prochaine_seance ? (
            <section className="auj-hero" aria-labelledby="auj-prochaine">
              <div className="auj-hero__kicker" id="auj-prochaine">
                Prochaine séance ·{' '}
                {data.prochaine_seance.en_cours ? 'en cours' : data.prochaine_seance.est_aujourdhui ? "aujourd'hui" : formatDateCourte(data.prochaine_seance.date)}
              </div>
              <h2>{data.prochaine_seance.classe_libelle}</h2>
              <div className="auj-hero__meta">
                <span>
                  {formatDateCourte(data.prochaine_seance.date)} · {formatHoraire(data.prochaine_seance.heure_debut, data.prochaine_seance.heure_fin)}
                </span>
                {data.prochaine_seance.lieu && <span>{data.prochaine_seance.lieu}</span>}
                <span>{data.prochaine_seance.libelle_complet}</span>
              </div>
              {data.prochaine_seance.remplace && <p className="auj-hero__note">Vous remplacez {data.prochaine_seance.remplace}</p>}
              {data.prochaine_seance.co_professeurs.length > 0 && <p className="auj-hero__note">Avec {data.prochaine_seance.co_professeurs.join(', ')}</p>}
              <div className="auj-hero__actions">
                <Link to={`/mes-ressources/${data.prochaine_seance.cours_id}`} className="auj-btn auj-btn--cta">
                  Liens du cours
                </Link>
                <Link to={`/mes-classes/${data.prochaine_seance.classe_id}`} className="auj-btn auj-btn--ghost">
                  La classe
                </Link>
              </div>
            </section>
          ) : (
            <p className="auj-vide">Aucune séance à venir.</p>
          )}

          {data.mois_soumis && (
            <Banner tone="info" role="status">
              <strong>Mois soumis à la direction.</strong> Vos heures ne sont plus modifiables ; une correction passe par la direction.
            </Banner>
          )}

          {!data.mois_soumis && (
            <section aria-labelledby="auj-encoder">
              <div className="auj-titre">
                <h2 id="auj-encoder">À encoder</h2>
                <span>
                  {data.a_encoder.length} séance{data.a_encoder.length > 1 ? 's' : ''}
                </span>
              </div>
              {data.a_encoder.length === 0 ? (
                <EmptyBlock icon="✅" title="Tout est à jour">
                  Rien à encoder pour le moment. Vos prochaines séances apparaîtront ici dès qu'elles auront commencé.
                </EmptyBlock>
              ) : (
                <ul className="auj-pile">
                  {data.a_encoder.map((s) => (
                    <li key={s.id} className={`auj-carte${sorties.includes(s.id) ? ' auj-carte--sort' : ''}`}>
                      <div className="auj-carte__titre">
                        {s.classe_libelle} · {s.libelle_complet}
                      </div>
                      <div className="auj-carte__meta">
                        {formatDateCourte(s.date)} · {formatHoraire(s.heure_debut, s.heure_fin)}
                      </div>
                      <div className="auj-carte__actions">
                        <button type="button" className="auj-btn auj-btn--cta" disabled={enCours === s.id} onClick={() => encoder(s)}>
                          Encoder {formatDuree(s.duree_par_defaut)}
                        </button>
                        <Link to="/timesheets" className="auj-lien">
                          Autre durée
                        </Link>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          )}

          <p className="auj-pied">
            <Link to="/timesheets">Voir mon mois et soumettre</Link>
          </p>
        </>
      )}

      {annulable && (
        <div className="auj-toast" role="status" aria-live="polite">
          <span>Encodé · {formatDuree(annulable.heures)}</span>
          <button type="button" onClick={annuler}>
            Annuler
          </button>
        </div>
      )}
    </div>
  );
}
