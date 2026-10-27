import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminTextarea } from '../AdminFormField';
import Banner from '../ui/Banner';
import { formatDateCourte } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';

/**
 * TS-05 : confirmation de la soumission du mois d'un professeur par la direction ou l'admin, en l'état. Le serveur
 * fournit l'éligibilité et les avertissements (`soumission_forcee`) ; un message au professeur est obligatoire.
 *
 * @param {object} props
 * @param {string} props.nom nom complet du professeur
 * @param {string} props.prenom
 * @param {string} props.titreMois « octobre 2026 »
 * @param {object} props.etat `soumission_forcee` du détail du mois
 * @param {boolean} props.envoi
 * @param {string|null} props.erreur
 * @param {(message: string) => void} props.onConfirmer
 * @param {() => void} props.onClose
 */
export default function SoumettreALaPlaceModal({ nom, prenom, titreMois, etat, envoi, erreur, onConfirmer, onClose }) {
  const [message, setMessage] = useState('');
  const valide = message.trim().length >= 3 && message.trim().length <= 500;

  return (
    <AdminModal
      isOpen
      title={`Soumettre le mois de ${nom}`}
      size="sm"
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton loading={envoi} disabled={!valide} onClick={() => onConfirmer(message.trim())}>
            Soumettre le mois de {prenom}
          </AdminButton>
        </>
      }
    >
      {erreur && <Banner tone="error" role="alert">{erreur}</Banner>}
      <p style={{ margin: '0 0 8px', fontSize: 13 }}>{titreMois}</p>
      <dl className="mm-recap">
        <div><dt>Heures</dt><dd>{formatHeures(etat.heures)}</dd></div>
        <div><dt>Montant brut</dt><dd>{formatEuros(etat.total_eur)}</dd></div>
        <div><dt>Lignes en brouillon</dt><dd>{etat.lignes}</dd></div>
      </dl>

      {!etat.mois_termine && (
        <Banner tone="warning">
          <strong>Le mois n'est pas terminé.</strong> Les séances à venir ne seront pas incluses, et {prenom} ne pourra plus ajouter d'heures pour ce mois.
        </Banner>
      )}
      {etat.seances_sans_heures.length > 0 && (
        <Banner tone="warning">
          <strong>{etat.seances_sans_heures.length} séance{etat.seances_sans_heures.length > 1 ? 's' : ''} passée{etat.seances_sans_heures.length > 1 ? 's' : ''} sans heures</strong>{' '}
          ({etat.seances_sans_heures.map((d) => formatDateCourte(d)).join(', ')}) : elles ne seront pas incluses.
        </Banner>
      )}
      {etat.depassements.length > 0 && (
        <Banner tone="info">
          Plafond journalier dépassé le {etat.depassements.map((d) => formatDateCourte(d)).join(', ')} : vous pourrez lisser à la validation.
        </Banner>
      )}

      <div style={{ border: '1px solid var(--c-border)', borderRadius: 8, background: 'var(--c-bg)', padding: '10px 12px', marginBottom: 10 }}>
        <strong>Conséquences</strong>
        <ul style={{ margin: '6px 0 0', paddingLeft: 18 }}>
          <li>Les {etat.lignes} ligne(s) en brouillon passent en « soumis » en l'état, d'un coup. Le mois passe en « À valider ».</li>
          <li>{prenom} ne pourra plus ajouter ni modifier d'heures pour ce mois. Une correction passera par vous (adaptation ou remise en brouillon).</li>
          <li>Une version « soumise » est enregistrée au nom de la direction. Ensuite : validation, signature du professeur, PDF.</li>
          <li>{prenom} est prévenu(e) par notification et par e-mail.</li>
        </ul>
      </div>

      <AdminFormField label="Message au professeur (obligatoire, 500 caractères max.)" htmlFor="soumission-forcee-message">
        <AdminTextarea
          id="soumission-forcee-message"
          rows={3}
          maxLength={500}
          value={message}
          placeholder="Ex. : échéance de paie, heures confirmées par téléphone"
          onChange={(e) => setMessage(e.target.value)}
        />
      </AdminFormField>
      <p style={{ fontSize: 12, margin: 0 }}>Ce message est repris dans la notification, l'e-mail et l'historique du mois.</p>
    </AdminModal>
  );
}
