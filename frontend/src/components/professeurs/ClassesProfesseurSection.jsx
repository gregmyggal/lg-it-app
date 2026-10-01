import { useState } from 'react';
import AdminButton from '../AdminButton';
import { Table, Th, Td, Tr } from '../ui/Table';
import StatutBadge from '../ui/StatutBadge';
import { Section } from '../ui/Card';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../ui/DataStates';
import LinkButton from '../ui/LinkButton';
import AssignerProfesseurModal from './AssignerProfesseurModal';
import TerminerAssignationModal from './TerminerAssignationModal';
import { useAssignationsProfesseur } from '../../hooks/useProfesseursClasses';
import { useClasses } from '../../hooks/useClasses';
import { useToast } from '../../hooks/useToast';
import { ROLES_PROFESSEUR, STATUTS_ASSIGNATION, statutAssignation } from '../../utils/statuts';
import { formatDate, libelleClasse } from '../../utils/dates';

/**
 * Section « Classes » de la fiche professeur (mock-up 04) : classes assignées avec co-professeurs, ajout d'une classe
 * avec le même aperçu de propagation que depuis la classe, fin d'assignation. Remplace l'assignation de cours.
 *
 * @param {object} props
 * @param {{id: number, prenom: string, nom: string}} props.professeur
 */
export default function ClassesProfesseurSection({ professeur }) {
  const toast = useToast();
  const assignations = useAssignationsProfesseur(professeur.id);
  const classes = useClasses({ statut: 'active' });
  const [ajout, setAjout] = useState(false);
  const [fin, setFin] = useState(null);
  const nom = `${professeur.prenom} ${professeur.nom}`.trim();

  const dejaActives = new Set((assignations.data || []).filter((a) => a.actif).map((a) => a.classe_id));
  const options = (classes.data || [])
    .filter((c) => !dejaActives.has(c.id))
    .map((c) => ({ value: String(c.id), label: `${libelleClasse(c)} (${c.annee_scolaire?.libelle}, P${c.periode?.numero})` }));

  function termine(message) {
    toast.success(message);
    assignations.reload();
  }

  return (
    <Section
      title="Classes"
      subtitle="Les classes où ce professeur enseigne : il est assigné à leurs sessions à venir"
      bodyPadding={false}
      actions={
        <AdminButton size="sm" onClick={() => setAjout(true)} disabled={classes.loading}>
          ＋ Ajouter une classe
        </AdminButton>
      }
    >
      {assignations.loading && <LoadingBlock message="Chargement des classes…" lignes={2} />}
      {assignations.error && <ErrorBlock message={assignations.error} onRetry={assignations.reload} />}
      {assignations.data?.length === 0 && (
        <EmptyBlock icon="🏫" title="Aucune classe assignée">
          Ajoutez une classe : {nom} la retrouvera dans « Mes classes » avec toutes ses sessions à venir.
        </EmptyBlock>
      )}
      {assignations.data?.length > 0 && (
        <Table caption={`Classes de ${nom}`} minWidth="760px">
          <thead>
            <tr>
              <Th>Classe</Th>
              <Th>Rôle (indicatif)</Th>
              <Th>Co-professeurs</Th>
              <Th>Depuis</Th>
              <Th>Statut</Th>
              <Th>Actions</Th>
            </tr>
          </thead>
          <tbody>
            {assignations.data.map((a) => (
              <Tr key={a.id}>
                <Td>
                  <strong>{libelleClasse(a.classe)}</strong>
                  <div style={{ fontSize: '13px' }}>{a.nb_sessions_assignees} session{a.nb_sessions_assignees > 1 ? 's' : ''}</div>
                </Td>
                <Td>
                  <StatutBadge table={ROLES_PROFESSEUR} valeur={a.role} />
                </Td>
                <Td>{a.co_professeurs?.length ? a.co_professeurs.map((p) => p.nom).join(', ') : '—'}</Td>
                <Td>
                  {formatDate(a.date_debut)}
                  {a.date_fin ? ` → ${formatDate(a.date_fin)}` : ''}
                </Td>
                <Td>
                  <StatutBadge table={STATUTS_ASSIGNATION} valeur={statutAssignation(a.actif)} />
                </Td>
                <Td>
                  <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                    <LinkButton to={`/admin/classes/${a.classe_id}`} size="sm">
                      Voir la classe
                    </LinkButton>
                    {a.can?.delete && (
                      <AdminButton size="sm" variant="secondary" onClick={() => setFin(a)}>
                        Terminer
                      </AdminButton>
                    )}
                  </div>
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}

      {ajout && (
        <AssignerProfesseurModal
          cote="professeur"
          fixeId={professeur.id}
          fixeNom={nom}
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
          ctx={{ cote: 'professeur', classeId: fin.classe_id, professeurId: professeur.id }}
          nomProfesseur={nom}
          nomClasse={libelleClasse(fin.classe)}
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
