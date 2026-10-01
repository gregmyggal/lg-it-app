import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import { terminerAssignation } from '../../hooks/useProfesseursClasses';
import { getErrorMessage } from '../../api/errors';

/**
 * Terminer l'assignation d'un professeur à une classe : retire le professeur des sessions à venir sans timesheet,
 * conserve les sessions passées et celles qui ont des heures encodées, garde l'historique (réactivation possible).
 *
 * @param {object} props
 * @param {{cote: 'classe'|'professeur', classeId: number, professeurId: number}} props.ctx
 * @param {string} props.nomProfesseur
 * @param {string} props.nomClasse
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onDone
 */
export default function TerminerAssignationModal({ ctx, nomProfesseur, nomClasse, onClose, onDone }) {
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function confirmer() {
    setEnvoi(true);
    setErreur(null);
    try {
      const { recapitulatif } = await terminerAssignation(ctx);
      const retirees = recapitulatif.sessions_retirees;
      const gardees = recapitulatif.sessions_conservees;
      onDone(
        `Assignation de ${nomProfesseur} terminée : retiré de ${retirees} session${retirees > 1 ? 's' : ''} à venir${
          gardees ? `, ${gardees} conservée${gardees > 1 ? 's' : ''} (heures encodées)` : ''
        }.`,
      );
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title={`Terminer l'assignation de ${nomProfesseur} ?`}
      size="sm"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Conserver l'assignation
          </AdminButton>
          <AdminButton variant="danger" onClick={confirmer} loading={envoi}>
            Terminer l'assignation
          </AdminButton>
        </>
      }
    >
      {erreur && (
        <Banner tone="error" role="alert">
          <strong>{erreur}</strong>
        </Banner>
      )}
      <p style={{ marginTop: 0 }}>
        {nomProfesseur} sera retiré des <strong>sessions à venir</strong> de « {nomClasse} » qui n'ont pas d'heures encodées.
      </p>
      <ul style={{ margin: 0, paddingLeft: '20px' }}>
        <li>Les sessions passées et celles avec heures encodées sont conservées.</li>
        <li>L'historique de l'assignation est gardé : vous pourrez la réactiver plus tard.</li>
      </ul>
    </AdminModal>
  );
}
