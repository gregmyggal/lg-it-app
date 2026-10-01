import { useEffect, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminSelect } from '../AdminFormField';
import Banner from '../ui/Banner';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';
import { annulerRemplacement, lignesSession, remplacerProfesseur } from '../../hooks/useProfesseursClasses';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { formatDate } from '../../utils/dates';

/**
 * Remplacement ponctuel d'un professeur sur UNE session (mock-up 03). Possible sur une session passée comme à venir
 * (pas annulée) et sans aucune contrainte liée aux heures : les timesheets existantes restent inchangées et
 * indépendantes. Permet aussi d'annuler un remplacement existant.
 *
 * @param {object} props
 * @param {object} props.session CourseSessionResource
 * @param {{value: string, label: string}[]} props.professeurs tous les professeurs actifs
 * @param {() => void} props.onClose
 * @param {(message: string, avertissements?: {message: string}[]) => void} props.onDone avertissements non bloquants (ex. conflit d'horaire du remplaçant)
 */
export default function RemplacerProfesseurModal({ session, professeurs, onClose, onDone }) {
  const [lignes, setLignes] = useState(null);
  const [remplace, setRemplace] = useState('');
  const [remplacant, setRemplacant] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  useEffect(() => {
    let annule = false;
    lignesSession(session.id)
      .then((data) => !annule && setLignes(data))
      .catch((err) => !annule && setMessage(getErrorMessage(err)));
    return () => {
      annule = true;
    };
  }, [session.id]);

  const presents = new Set((lignes || []).map((l) => l.professeur_id));
  const remplacables = (lignes || []).filter((l) => !l.remplace);
  const dejaRemplaces = (lignes || []).filter((l) => l.remplace);
  const candidats = professeurs.filter((p) => !presents.has(Number(p.value)));
  const titreSession = `${session.libelle.toLowerCase()} du ${formatDate(session.date)}`;

  async function soumettre(e) {
    e.preventDefault();
    setEnvoi(true);
    setMessage(null);
    setErreurs({});
    try {
      const res = await remplacerProfesseur(session.id, {
        professeur_remplace_id: Number(remplace),
        professeur_remplacant_id: Number(remplacant),
      });
      const nomA = remplacables.find((l) => String(l.professeur_id) === remplace)?.professeur?.nom;
      const nomB = candidats.find((p) => p.value === remplacant)?.label;
      onDone(
        `${nomB} remplace ${nomA} sur la ${titreSession}. Les autres sessions et les heures déjà encodées ne changent pas.`,
        res.avertissements || [],
      );
    } catch (err) {
      setErreurs(getFieldErrors(err));
      setMessage(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  async function annuler(ligne) {
    setEnvoi(true);
    setMessage(null);
    try {
      await annulerRemplacement(session.id, ligne.professeur_id);
      onDone(`Remplacement annulé : ${ligne.professeur.nom} est de nouveau assigné à la ${titreSession}.`);
    } catch (err) {
      setMessage(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title={`Remplacer un professeur — ${session.libelle}, ${formatDate(session.date)}`}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Fermer sans modifier
          </AdminButton>
          <AdminButton type="submit" form="form-remplacement" disabled={!remplace || !remplacant || envoi} loading={envoi}>
            Remplacer sur cette session
          </AdminButton>
        </>
      }
    >
      <form id="form-remplacement" onSubmit={soumettre} noValidate>
        {message && (
          <Banner tone="error" role="alert">
            <strong>{message}</strong>
          </Banner>
        )}
        <AdminFormField label="Professeur remplacé" htmlFor="remp-remplace" required error={erreurs.professeur_remplace_id}>
          <AdminSelect
            id="remp-remplace"
            value={remplace}
            placeholder="Choisir le professeur à remplacer"
            options={remplacables.map((l) => ({ value: String(l.professeur_id), label: l.professeur.nom }))}
            onChange={(e) => setRemplace(e.target.value)}
          />
        </AdminFormField>
        <AdminFormField label="Remplaçant" htmlFor="remp-remplacant" required error={erreurs.professeur_remplacant_id}>
          <AdminSelect
            id="remp-remplacant"
            value={remplacant}
            placeholder="Choisir le remplaçant"
            options={candidats}
            onChange={(e) => setRemplacant(e.target.value)}
          />
        </AdminFormField>
        <p style={{ color: ADMIN_COLORS.textSecondary, fontSize: '13px', margin: 0 }}>
          Seule la <strong>{titreSession}</strong> est concernée. Les heures déjà encodées par le professeur remplacé restent inchangées ; le
          remplaçant encode les siennes, de façon indépendante.
        </p>

        {dejaRemplaces.length > 0 && (
          <div style={{ marginTop: ADMIN_SPACING.xl }}>
            <strong style={{ fontSize: '13px' }}>Remplacements en cours sur cette session</strong>
            <ul style={{ listStyle: 'none', margin: `${ADMIN_SPACING.sm} 0 0`, padding: 0 }}>
              {dejaRemplaces.map((l) => (
                <li key={l.id} style={{ display: 'flex', alignItems: 'center', gap: ADMIN_SPACING.md, padding: `${ADMIN_SPACING.xs} 0` }}>
                  <span>
                    {l.professeur.nom} → remplacé par {l.remplace_par?.nom}
                  </span>
                  <AdminButton size="sm" variant="secondary" disabled={envoi} onClick={() => annuler(l)}>
                    Annuler ce remplacement
                  </AdminButton>
                </li>
              ))}
            </ul>
          </div>
        )}
      </form>
    </AdminModal>
  );
}
