import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import Banner from '../components/ui/Banner';
import StatutBadge from '../components/ui/StatutBadge';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../components/ui/DataStates';
import { useAccueilDirection } from '../hooks/useDirection';
import { formatHoraire, MOIS_LONGS } from '../utils/dates';
import { avecTransition } from '../utils/transition';

function titreDuJour(iso) {
  const [annee, mois, jour] = iso.split('-').map(Number);
  const nom = new Date(annee, mois - 1, jour).toLocaleDateString('fr-BE', { weekday: 'long' });
  return `${nom.charAt(0).toUpperCase()}${nom.slice(1)} ${jour} ${MOIS_LONGS[mois - 1]}`;
}

/** « Aujourd'hui » de la direction (UI-M6) : qui enseigne où, aujourd'hui et demain, et ce qui demande une action. */
export default function AujourdhuiDirectionPage() {
  const { user } = useAuth();
  const accueil = useAccueilDirection();
  const navigate = useNavigate();
  const data = accueil.data;

  const groupes = data
    ? [
        ['En cours', data.seances.filter((s) => s.en_cours)],
        ["Plus tard aujourd'hui", data.seances.filter((s) => s.est_aujourdhui && !s.en_cours && !s.terminee)],
        ['Terminées', data.seances.filter((s) => s.terminee)],
        ['Demain', data.seances.filter((s) => !s.est_aujourdhui)],
      ].filter(([, liste]) => liste.length > 0)
    : [];

  return (
    <div className="auj">
      {accueil.loading && !data && <LoadingBlock message="Chargement de la journée…" lignes={4} />}
      {accueil.error && !data && <ErrorBlock message="Impossible de charger la journée. Vérifiez votre connexion puis réessayez." onRetry={accueil.reload} />}

      {data && (
        <>
          <header className="auj-salut">
            <div>
              <small>{titreDuJour(data.date)}</small>
              <h1>Bonjour {user?.name?.split(' ')[0]}</h1>
            </div>
          </header>

          {data.alertes.sans_professeur > 0 && (
            <Banner tone="error" role="status">
              <strong>
                {data.alertes.sans_professeur} séance{data.alertes.sans_professeur > 1 ? 's' : ''} sans professeur
              </strong>{' '}
              dans les 7 prochains jours.
            </Banner>
          )}
          {data.alertes.timesheets_a_valider > 0 && (
            <Banner
              tone="info"
              actions={
                <Link to="/admin/timesheets" className="auj-lien">
                  Ouvrir
                </Link>
              }
            >
              <strong>
                {data.alertes.timesheets_a_valider} timesheet{data.alertes.timesheets_a_valider > 1 ? 's' : ''} à valider
              </strong>
            </Banner>
          )}

          {groupes.length === 0 && (
            <EmptyBlock icon="🗓️" title="Aucune séance aujourd'hui ni demain">
              Les séances des deux prochains jours apparaîtront ici avec leurs professeurs.
            </EmptyBlock>
          )}

          {groupes.map(([titre, liste]) => (
            <section key={titre} aria-label={titre}>
              <h2 className="dir-groupe">{titre}</h2>
              {liste.map((s) => (
                <button key={s.id} type="button" className={`dir-carte${s.professeurs.length === 0 ? ' dir-carte--vide' : ''}`} onClick={() => avecTransition(() => navigate(`/seances/${s.id}`))} aria-label={`Ouvrir la séance ${s.classe_libelle} ${formatHoraire(s.heure_debut, s.heure_fin)}`}>
                  <span className="dir-carte__heure">{formatHoraire(s.heure_debut, s.heure_fin)}</span>
                  <span className="dir-carte__titre"> {s.classe_libelle} · {s.libelle_complet}</span>
                  <span className="dir-carte__meta">
                    {s.lieu && <span>{s.lieu}</span>}
                    {s.professeurs.length === 0 ? (
                      <StatutBadge label="Sans professeur" tone="error" />
                    ) : (
                      s.professeurs.map((p) => <StatutBadge key={p.id} label={p.nom} tone={p.origine === 'classe' ? 'primary' : 'warning'} />)
                    )}
                  </span>
                </button>
              ))}
            </section>
          ))}
        </>
      )}
    </div>
  );
}
