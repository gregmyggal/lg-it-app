import { useState } from 'react';
import AdminButton from '../AdminButton';
import AdminModal from '../AdminModal';
import Banner from '../ui/Banner';
import { AdminCheckbox, AdminFormField, AdminInput, AdminTextarea } from '../AdminFormField';
import { STATUTS_TIMESHEET } from '../../utils/statuts';
import { formatHeures } from '../../utils/format';
import { getErrorMessage, getFieldErrors, getStatus } from '../../api/errors';
import { supprimerClasse } from '../../hooks/useClasses';
import { ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const MOTIF_MIN = 10;

function pluriel(n, singulier, plurielTexte = `${singulier}s`) {
  return `${n} ${n > 1 ? plurielTexte : singulier}`;
}

/** Résumé de l'historique renvoyé par le 409 de DELETE /classes/{id} (CLS-08). */
function ResumeHistorique({ resume }) {
  const { seances, heures, professeurs } = resume;
  return (
    <ul style={{ margin: `0 0 ${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`, padding: 0 }}>
      <li>
        {pluriel(seances.passees, 'séance passée', 'séances passées')} · {pluriel(seances.annulees, 'annulée')} · {seances.a_venir} à venir
      </li>
      <li>
        Heures encodées :{' '}
        {heures.nb === 0
          ? 'aucune'
          : heures.par_statut
              .map((s) => `${s.nb} ${(STATUTS_TIMESHEET[s.statut]?.label || s.statut).toLowerCase()} (${formatHeures(s.total)})`)
              .join(' · ')}
      </li>
      {professeurs.length > 0 && <li>Professeurs : {professeurs.map((p) => p.nom).join(', ')}</li>}
    </ul>
  );
}

/**
 * CLS-08 : la classe a un historique (409). Archiver reste l'action recommandée ; « Supprimer quand même… »
 * ouvre la confirmation renforcée (motif, nom de la classe, case irréversible). Bloqué si fiche générée.
 */
export function SuppressionClasseModal({ classe, titre, refus, peutArchiver, archivageEnCours, onArchiver, onClose, onSupprimee }) {
  const [etape, setEtape] = useState('historique'); // 'historique' | 'forcer'
  const [motif, setMotif] = useState('');
  const [nom, setNom] = useState('');
  const [compris, setCompris] = useState(false);
  const [envoi, setEnvoi] = useState(false);
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const { resume, forcable } = refus;
  const nomAttendu = classe.titre;
  const pret = motif.trim().length >= MOTIF_MIN && nom.trim() !== '' && compris;

  async function forcer(e) {
    e.preventDefault();
    if (!pret) return;
    setEnvoi(true);
    setErreurs({});
    setMessage(null);
    try {
      await supprimerClasse(classe.id, { force: true, motif: motif.trim(), confirmation_nom: nom.trim() });
      onSupprimee(resume?.heures?.nb ?? 0);
    } catch (err) {
      setErreurs(getFieldErrors(err));
      setMessage(getStatus(err) === 422 ? null : getErrorMessage(err, "La classe n'a pas pu être supprimée."));
    } finally {
      setEnvoi(false);
    }
  }

  if (etape === 'historique') {
    return (
      <AdminModal
        isOpen
        title="Cette classe a un historique"
        size="md"
        onClose={onClose}
        footer={
          <>
            <AdminButton variant="secondary" onClick={onClose}>
              Fermer
            </AdminButton>
            {peutArchiver && (
              <AdminButton onClick={onArchiver} loading={archivageEnCours}>
                Archiver la classe
              </AdminButton>
            )}
          </>
        }
      >
        <p style={{ marginTop: 0 }}>
          <strong>« {titre} »</strong>
        </p>
        {resume ? <ResumeHistorique resume={resume} /> : <p>{refus.message}</p>}
        {forcable ? (
          <p>L'archivage garde tout l'historique et retire la classe des plannings. C'est l'option recommandée.</p>
        ) : (
          <Banner tone="error">
            <strong>Suppression impossible.</strong> {refus.message}
          </Banner>
        )}
        {forcable && (
          <AdminButton variant="ghost" size="sm" onClick={() => setEtape('forcer')}>
            Supprimer quand même…
          </AdminButton>
        )}
      </AdminModal>
    );
  }

  const nbSeances = resume?.seances?.total ?? 0;
  const nbHeures = resume?.heures?.nb ?? 0;
  return (
    <AdminModal
      isOpen
      title="Supprimer définitivement ?"
      size="md"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={() => setEtape('historique')}>
            ← Retour
          </AdminButton>
          {peutArchiver && (
            <AdminButton variant="secondary" onClick={onArchiver} loading={archivageEnCours}>
              Archiver plutôt
            </AdminButton>
          )}
          <AdminButton type="submit" form="form-forcer-suppression" variant="danger" disabled={!pret} loading={envoi}>
            Supprimer définitivement
          </AdminButton>
        </>
      }
    >
      <form id="form-forcer-suppression" onSubmit={forcer} noValidate>
        {message && (
          <Banner tone="error">
            <strong>{message}</strong>
          </Banner>
        )}
        <Banner tone="error">
          <strong>Irréversible.</strong> {pluriel(nbSeances, 'séance')} et leurs affectations de professeurs seront supprimées avec la classe.
        </Banner>
        {nbHeures > 0 && (
          <Banner tone="info">
            {nbHeures > 1 ? `Les ${nbHeures} heures encodées` : "L'heure encodée"} ({formatHeures(resume.heures.total)}) {nbHeures > 1 ? 'sont' : 'est'}{' '}
            <strong>{nbHeures > 1 ? 'conservées' : 'conservée'}</strong> dans les feuilles des professeurs, sans lien vers la séance.
          </Banner>
        )}
        <AdminFormField label="Motif" htmlFor="forcer-motif" required error={erreurs.motif} description={`Au moins ${MOTIF_MIN} caractères, conservé dans l'historique des heures.`}>
          <AdminTextarea id="forcer-motif" rows={2} value={motif} onChange={(e) => setMotif(e.target.value)} />
        </AdminFormField>
        <AdminFormField
          label="Nom de la classe"
          htmlFor="forcer-nom"
          required
          error={erreurs.confirmation_nom}
          description={<>Tapez « <strong>{nomAttendu}</strong> » pour confirmer.</>}
        >
          <AdminInput id="forcer-nom" value={nom} onChange={(e) => setNom(e.target.value)} autoComplete="off" />
        </AdminFormField>
        <AdminCheckbox id="forcer-compris" label="Je comprends que cette action est irréversible" checked={compris} onChange={(e) => setCompris(e.target.checked)} />
      </form>
    </AdminModal>
  );
}
