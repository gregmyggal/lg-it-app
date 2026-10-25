import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import { useMaSignature } from '../../hooks/useSignature';
import { formatDateCourte } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';

/**
 * Récapitulatif avant la soumission du mois (UI-M4) : ce qui part, ce qui manque, et le rappel de créer sa signature une fois.
 * La soumission ne signe rien : la direction valide, puis le professeur signe avec son spécimen (SIG-01).
 *
 * @param {object} props
 * @param {object} props.data réponse de `GET /timesheets/mon-mois`
 * @param {string} props.titreMois « Octobre 2026 »
 * @param {boolean} props.envoi
 * @param {() => void} props.onConfirmer
 * @param {() => void} props.onCreerSignature
 * @param {() => void} props.onClose
 */
export default function SoumettreMoisModal({ data, titreMois, envoi, onConfirmer, onCreerSignature, onClose }) {
  const signature = useMaSignature();
  const concernees = data.sessions.filter((s) => !s.annulee && s.peut_encoder);
  const encodees = concernees.filter((s) => s.encodage !== 'a_encoder').length;
  const oubliees = concernees.filter((s) => s.encodage === 'a_encoder' && !s.est_a_venir);
  const depassements = data.synthese.depassements || [];

  return (
    <AdminModal
      isOpen
      title={`Soumettre ${titreMois.toLowerCase()}`}
      size="sm"
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Pas maintenant
          </AdminButton>
          <AdminButton loading={envoi} onClick={onConfirmer}>
            Soumettre le mois
          </AdminButton>
        </>
      }
    >
      <dl className="mm-recap">
        <div>
          <dt>Heures</dt>
          <dd>{formatHeures(data.synthese.heures)}</dd>
        </div>
        <div>
          <dt>Montant brut</dt>
          <dd>{formatEuros(data.synthese.montant)}</dd>
        </div>
        <div>
          <dt>Séances encodées</dt>
          <dd>
            {encodees}/{concernees.length}
          </dd>
        </div>
      </dl>

      {oubliees.length > 0 && (
        <Banner tone="warning">
          <strong>
            {oubliees.length} séance{oubliees.length > 1 ? 's' : ''} passée{oubliees.length > 1 ? 's' : ''} sans heures
          </strong>{' '}
          ({oubliees.map((s) => formatDateCourte(s.date)).join(', ')}). Fermez cette fenêtre pour les encoder, ou soumettez quand même : le mois ne pourra plus être modifié.
        </Banner>
      )}
      {depassements.length > 0 && (
        <Banner tone="info">
          Plafond journalier dépassé le {depassements.map((d) => formatDateCourte(d.date)).join(', ')} : la direction proposera un lissage, rien à faire de votre côté.
        </Banner>
      )}
      {!signature.loading && !signature.error && !signature.specimen && (
        <Banner
          tone="info"
          actions={
            <AdminButton size="sm" variant="secondary" onClick={onCreerSignature}>
              Créer ma signature
            </AdminButton>
          }
        >
          Créez votre signature une seule fois : vous signerez ensuite en un tap quand la direction aura validé vos heures.
        </Banner>
      )}

      <p style={{ margin: 0, fontSize: 14 }}>
        Vous ne pouvez soumettre ce mois qu'une seule fois, entièrement : toutes vos heures partent ensemble. Ensuite, vous ne pourrez plus ajouter ni modifier d'heures pour ce mois.
      </p>
    </AdminModal>
  );
}
