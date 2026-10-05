import { useEffect, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect } from '../AdminFormField';
import Banner from '../ui/Banner';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';
import { apercuAssignation, assignerProfesseur } from '../../hooks/useProfesseursClasses';
import { getErrorData, getErrorMessage, getFieldErrors } from '../../api/errors';
import { optionsStatut, ROLES_PROFESSEUR } from '../../utils/statuts';
import { formatDateCourte } from '../../utils/dates';
import RecapitulatifPropagation from './RecapitulatifPropagation';

/**
 * Ajout d'une assignation professeur ⇄ classe avec aperçu de propagation (mock-ups 03 et 04).
 * Les deux côtés (depuis la classe, depuis le professeur) passent par la même modale et le même service API.
 * Un conflit d'horaire est bloquant : la confirmation est désactivée et les conflits sont listés.
 *
 * @param {object} props
 * @param {'classe'|'professeur'} props.cote côté de départ
 * @param {number} props.fixeId id de la classe (cote 'classe') ou du professeur (cote 'professeur')
 * @param {string} props.fixeNom libellé affiché de l'élément fixe
 * @param {{value: string, label: string}[]} props.options choix à faire : professeurs (cote 'classe') ou classes
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onDone appelée après succès, avec le message de confirmation
 */
export default function AssignerProfesseurModal({ cote, fixeId, fixeNom, options, onClose, onDone }) {
  const [choix, setChoix] = useState('');
  const [role, setRole] = useState('co_enseignant');
  const [dateDebut, setDateDebut] = useState('');
  const [apercu, setApercu] = useState({ etat: 'attente' }); // attente | chargement | ok | erreur
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  const ctx = {
    cote,
    classeId: cote === 'classe' ? fixeId : Number(choix),
    professeurId: cote === 'classe' ? Number(choix) : fixeId,
  };
  const payload = {
    [cote === 'classe' ? 'professeur_id' : 'classe_id']: Number(choix),
    role,
    ...(dateDebut ? { date_debut: dateDebut } : {}),
  };

  useEffect(() => {
    if (!choix) {
      setApercu({ etat: 'attente' });
      return undefined;
    }
    let annule = false;
    setApercu({ etat: 'chargement' });
    apercuAssignation(ctx, payload)
      .then((data) => !annule && setApercu({ etat: 'ok', data }))
      .catch((err) => !annule && setApercu({ etat: 'erreur', message: getErrorMessage(err), erreurs: getFieldErrors(err) }));
    return () => {
      annule = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [choix, dateDebut]);

  const conflits = apercu.etat === 'ok' ? apercu.data.conflits || [] : [];
  const peutConfirmer = apercu.etat === 'ok' && conflits.length === 0 && !envoi;
  const labelChoix = cote === 'classe' ? 'Professeur' : 'Classe';

  async function soumettre(e) {
    e.preventDefault();
    if (!peutConfirmer) return;
    setEnvoi(true);
    setMessage(null);
    setErreurs({});
    try {
      const { recapitulatif } = await assignerProfesseur(ctx, payload);
      const nom = options.find((o) => o.value === choix)?.label;
      const n = recapitulatif.sessions_assignees;
      onDone(
        `${cote === 'classe' ? nom : fixeNom} assigné${n > 1 ? '' : ''} à ${n} session${n > 1 ? 's' : ''}${
          recapitulatif.sessions_passees_ignorees ? ` (${recapitulatif.sessions_passees_ignorees} passées non modifiées)` : ''
        }.`,
      );
    } catch (err) {
      const data = getErrorData(err);
      if (data.conflits) setApercu({ etat: 'ok', data: { ...(apercu.data || {}), conflits: data.conflits } });
      setErreurs(getFieldErrors(err));
      setMessage(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title={cote === 'classe' ? `Ajouter un professeur à ${fixeNom}` : `Ajouter une classe à ${fixeNom}`}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Fermer sans modifier
          </AdminButton>
          <AdminButton type="submit" form="form-assignation" disabled={!peutConfirmer} loading={envoi}>
            {apercu.etat === 'ok' && apercu.data.sessions_assignees
              ? `Assigner à ${apercu.data.sessions_assignees} session${apercu.data.sessions_assignees > 1 ? 's' : ''}`
              : 'Assigner'}
          </AdminButton>
        </>
      }
    >
      <form id="form-assignation" onSubmit={soumettre} noValidate>
        {message && (
          <Banner tone="error" role="alert">
            <strong>{message}</strong>
          </Banner>
        )}
        <AdminFormField label={labelChoix} htmlFor="assign-choix" required error={erreurs.professeur_id || erreurs.classe_id}>
          <AdminSelect
            id="assign-choix"
            value={choix}
            placeholder={`Choisir un${cote === 'classe' ? ' professeur' : 'e classe'}`}
            options={options}
            onChange={(e) => {
              setChoix(e.target.value);
              setMessage(null);
            }}
          />
        </AdminFormField>
        <AdminFormField
          label="Rôle (indicatif)"
          htmlFor="assign-role"
          description="Le rôle est informatif : il n'a aucun effet sur la rémunération ni sur les heures encodées."
        >
          <AdminSelect id="assign-role" value={role} options={optionsStatut(ROLES_PROFESSEUR)} onChange={(e) => setRole(e.target.value)} />
        </AdminFormField>
        <AdminFormField
          label="À partir du (facultatif)"
          htmlFor="assign-debut"
          description="Par défaut : aujourd'hui. Les sessions passées ne sont jamais modifiées."
          error={erreurs.date_debut}
        >
          <AdminInput id="assign-debut" type="date" value={dateDebut} onChange={(e) => setDateDebut(e.target.value)} />
        </AdminFormField>

        <div aria-live="polite" style={{ marginTop: ADMIN_SPACING.lg }}>
          {apercu.etat === 'chargement' && <p style={{ color: ADMIN_COLORS.textSecondary }}>Calcul des sessions concernées…</p>}
          {apercu.etat === 'erreur' && (
            <Banner tone="error">
              <strong>{apercu.message}</strong>
            </Banner>
          )}
          {apercu.etat === 'ok' && <RecapitulatifPropagation apercu={apercu.data} />}
          {conflits.length > 0 && (
            <Banner tone="error" role="alert">
              <strong>Conflit d'horaire : l'assignation est impossible.</strong>
              <ul style={{ margin: `${ADMIN_SPACING.sm} 0 0`, paddingLeft: '20px' }}>
                {conflits.slice(0, 5).map((c) => (
                  <li key={`${c.session_id}-${c.session_en_conflit_id}`}>
                    {formatDateCourte(c.date)} — déjà assigné à « {c.classe} » de {c.heure_debut} à {c.heure_fin}
                  </li>
                ))}
                {conflits.length > 5 && <li>… et {conflits.length - 5} autre{conflits.length - 5 > 1 ? 's' : ''}</li>}
              </ul>
            </Banner>
          )}
        </div>
      </form>
    </AdminModal>
  );
}
