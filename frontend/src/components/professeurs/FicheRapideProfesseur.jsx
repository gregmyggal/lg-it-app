import { Link } from 'react-router-dom';
import AdminButton from '../AdminButton';
import { LoadingBlock } from '../ui/DataStates';
import { useApiQuery } from '../../hooks/useApiQuery';
import { chargerSeancesProfesseur } from '../../hooks/useDirection';
import { formatDateCourte, formatHoraire } from '../../utils/dates';

const jour = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const nettoyer = (tel) => String(tel).replace(/[^\d+]/g, '');

/**
 * En-tête actionnable d'une fiche professeur (UI-M7) : joindre en 1 tap, séances de la semaine (chacune ouvre la fiche
 * séance), remplacer, éditer, archiver. Mêmes actions sur ordinateur et smartphone.
 */
export default function FicheRapideProfesseur({ professeur, actif, onRemplacer, onEditer, onArchiver }) {
  const debut = new Date();
  const fin = new Date();
  fin.setDate(fin.getDate() + 6);
  const seances = useApiQuery(() => chargerSeancesProfesseur(professeur.id, jour(debut), jour(fin)), [professeur.id], { enabled: actif });
  const liste = seances.data || [];

  return (
    <section className="fiche-rapide" aria-label="Actions rapides">
      <div className="fiche-rapide__contact">
        {professeur.telephone && (
          <a className="fiche-rapide__btn" href={`tel:${nettoyer(professeur.telephone)}`}>
            <span aria-hidden="true">📞</span>Appeler
          </a>
        )}
        {professeur.telephone && (
          <a className="fiche-rapide__btn" href={`sms:${nettoyer(professeur.telephone)}`}>
            <span aria-hidden="true">💬</span>SMS
          </a>
        )}
        {professeur.email && (
          <a className="fiche-rapide__btn" href={`mailto:${professeur.email}`}>
            <span aria-hidden="true">✉️</span>Email
          </a>
        )}
      </div>

      {actif && (
        <>
          <h2 className="fiche-rapide__titre">Cette semaine</h2>
          {seances.loading && !seances.data && <LoadingBlock message="Chargement des séances…" lignes={2} />}
          {seances.error && <p role="alert">Impossible de charger les séances.</p>}
          {seances.data && liste.length === 0 && <p className="fiche-rapide__vide">Aucune séance cette semaine.</p>}
          {liste.length > 0 && (
            <ul className="fiche-rapide__liste">
              {liste.slice(0, 5).map((s) => (
                <li key={s.id}>
                  <Link to={`/seances/${s.id}`}>
                    <strong>
                      {s.classe_libelle} · {s.libelle_complet}
                    </strong>
                    <small>
                      {formatDateCourte(s.date)} · {formatHoraire(s.heure_debut, s.heure_fin)}
                    </small>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </>
      )}

      <div className="fiche-rapide__actions">
        {actif && (
          <AdminButton size="sm" onClick={onRemplacer}>
            Remplacer sur plusieurs séances
          </AdminButton>
        )}
        <AdminButton size="sm" variant="secondary" onClick={onEditer}>
          Éditer
        </AdminButton>
        {actif && (
          <AdminButton size="sm" variant="secondary" onClick={onArchiver}>
            Archiver…
          </AdminButton>
        )}
      </div>
    </section>
  );
}
