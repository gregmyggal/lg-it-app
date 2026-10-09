import { useState } from 'react';
import AdminButton from '../AdminButton';
import { Table, Th, Td, Tr } from '../ui/Table';
import StatutBadge from '../ui/StatutBadge';
import { Section } from '../ui/Card';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../ui/DataStates';
import { AdminSelect } from '../AdminFormField';
import AssignerProfesseurModal from './AssignerProfesseurModal';
import TerminerAssignationModal from './TerminerAssignationModal';
import {
  modifierAssignation,
  useAssignationsClasse,
  useProfesseursListe,
} from '../../hooks/useProfesseursClasses';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage } from '../../api/errors';
import { optionsStatut, ROLES_PROFESSEUR, STATUTS_ASSIGNATION, statutAssignation } from '../../utils/statuts';
import { formatDate } from '../../utils/dates';

/**
 * Section « Professeurs » du détail d'une classe (mock-up 03) : liste des assignations, ajout avec aperçu de
 * propagation, changement de rôle (indicatif), fin d'assignation. `onChange` demande le rechargement des sessions.
 *
 * @param {object} props
 * @param {object} props.classe ClasseResource
 * @param {string} props.titreClasse libellé de la classe
 * @param {() => void} props.onChange
 */
export default function ProfesseursClasseSection({ classe, titreClasse, onChange }) {
  const toast = useToast();
  const assignations = useAssignationsClasse(classe.id);
  const professeurs = useProfesseursListe();
  const [ajout, setAjout] = useState(false);
  const [fin, setFin] = useState(null); // assignation à terminer
  const peutGerer = classe.can?.update;

  function termine(message) {
    toast.success(message);
    assignations.reload();
    onChange();
  }

  async function changerRole(a, role) {
    try {
      await modifierAssignation({ cote: 'classe', classeId: classe.id, professeurId: a.professeur_id }, { role });
      toast.success(`Rôle de ${a.professeur.nom} : ${ROLES_PROFESSEUR[role].label}. Aucun effet sur la rémunération.`);
      assignations.reload();
      onChange();
    } catch (err) {
      toast.error(getErrorMessage(err, 'Le rôle n\'a pas pu être modifié.'));
    }
  }

  const actifs = new Set((assignations.data || []).filter((a) => a.actif).map((a) => a.professeur_id));
  const options = (professeurs.data || [])
    .filter((p) => p.statut !== 'inactif' && !actifs.has(p.id))
    .map((p) => ({ value: String(p.id), label: p.nom }));

  return (
    <Section
      title="Professeurs"
      subtitle="Assignés à la classe : ils sont automatiquement assignés à ses sessions à venir et à ses sessions passées sans professeur"
      bodyPadding={false}
      actions={
        peutGerer && (
          <AdminButton size="sm" onClick={() => setAjout(true)} disabled={professeurs.loading}>
            ＋ Ajouter un professeur
          </AdminButton>
        )
      }
    >
      {assignations.loading && <LoadingBlock message="Chargement des professeurs…" lignes={2} />}
      {assignations.error && <ErrorBlock message={assignations.error} onRetry={assignations.reload} />}
      {assignations.data?.length === 0 && (
        <EmptyBlock icon="👩‍🏫" title="Aucun professeur assigné">
          Assignez un professeur pour qu'il retrouve cette classe et ses sessions dans « Mes classes ».
        </EmptyBlock>
      )}
      {assignations.data?.length > 0 && (
        <Table caption="Professeurs de la classe" minWidth="680px" cards>
          <thead>
            <tr>
              <Th>Professeur</Th>
              <Th>Rôle (indicatif)</Th>
              <Th>Depuis</Th>
              <Th>Sessions</Th>
              <Th>Statut</Th>
              <Th>Actions</Th>
            </tr>
          </thead>
          <tbody>
            {assignations.data.map((a) => (
              <Tr key={a.id}>
                <Td label="Professeur">
                  <strong>{a.professeur.nom}</strong>
                </Td>
                <Td label="Rôle">
                  {a.can?.update && a.actif ? (
                    <AdminSelect
                      aria-label={`Rôle de ${a.professeur.nom}`}
                      value={a.role}
                      options={optionsStatut(ROLES_PROFESSEUR)}
                      onChange={(e) => changerRole(a, e.target.value)}
                    />
                  ) : (
                    <StatutBadge table={ROLES_PROFESSEUR} valeur={a.role} />
                  )}
                </Td>
                <Td label="Depuis">
                  {formatDate(a.date_debut)}
                  {a.date_fin ? ` → ${formatDate(a.date_fin)}` : ''}
                </Td>
                <Td label="Sessions">{a.nb_sessions_assignees}</Td>
                <Td label="Statut">
                  <StatutBadge table={STATUTS_ASSIGNATION} valeur={statutAssignation(a.actif)} />
                </Td>
                <Td label="Actions">
                  {a.can?.delete && (
                    <AdminButton size="sm" variant="secondary" onClick={() => setFin(a)}>
                      Terminer
                    </AdminButton>
                  )}
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}

      {ajout && (
        <AssignerProfesseurModal
          cote="classe"
          fixeId={classe.id}
          fixeNom={titreClasse}
          options={options}
          onClose={() => setAjout(false)}
          onDone={(message) => {
            setAjout(false);
            termine(message);
          }}
        />
      )}
      {fin && (
        <TerminerAssignationModal
          ctx={{ cote: 'classe', classeId: classe.id, professeurId: fin.professeur_id }}
          nomProfesseur={fin.professeur.nom}
          nomClasse={titreClasse}
          onClose={() => setFin(null)}
          onDone={(message) => {
            setFin(null);
            termine(message);
          }}
        />
      )}
    </Section>
  );
}
