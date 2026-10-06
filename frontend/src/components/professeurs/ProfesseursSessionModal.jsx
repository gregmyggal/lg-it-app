import { useEffect, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminSelect } from '../AdminFormField';
import Banner from '../ui/Banner';
import StatutBadge from '../ui/StatutBadge';
import { ORIGINES_SESSION, ROLES_PROFESSEUR } from '../../utils/statuts';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';
import { ajouterProfesseurSession, annulerRemplacement, lignesSession, remplacerProfesseur, retirerProfesseurSession } from '../../hooks/useProfesseursClasses';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { formatDate } from '../../utils/dates';
import { libelleSession, libelleSessionPhrase } from '../../utils/classes';

/**
 * Professeurs d'UNE session (CLS-04, mock-ups 01/02) : liste, ajout ponctuel (rôle « Principal » par défaut), retrait d'un
 * ajout tant que ses heures ne sont pas encodées, et remplacement ponctuel. Possible sur une session passée comme à venir
 * (pas annulée) : les heures déjà encodées ne changent jamais.
 *
 * @param {object} props
 * @param {object} props.session CourseSessionResource
 * @param {{value: string, label: string}[]} props.professeurs tous les professeurs actifs
 * @param {() => void} props.onClose
 * @param {(message: string, avertissements?: {message: string}[]) => void} props.onDone avertissements non bloquants (conflit d'horaire)
 */
export default function ProfesseursSessionModal({ session, professeurs, onClose, onDone }) {
  const [lignes, setLignes] = useState(null);
  const [onglet, setOnglet] = useState('ajouter');
  const [ajoute, setAjoute] = useState('');
  const [role, setRole] = useState('principal');
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
  // PROF-02 : un remplacé sans remplaçant (remplaçant archivé ou supprimé) peut recevoir un nouveau remplaçant.
  const remplacables = (lignes || []).filter((l) => !l.remplace || !l.remplace_par_professeur_id);
  const candidats = professeurs.filter((p) => !presents.has(Number(p.value)));
  const titreSession = `${libelleSessionPhrase(session)} du ${formatDate(session.date)}`;
  const passee = new Date(`${session.date}T23:59:59`) < new Date();

  async function executer(action) {
    setEnvoi(true);
    setMessage(null);
    setErreurs({});
    try {
      await action();
    } catch (err) {
      setErreurs(getFieldErrors(err));
      setMessage(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  function soumettre(e) {
    e.preventDefault();
    if (onglet === 'ajouter') {
      return executer(async () => {
        const res = await ajouterProfesseurSession(session.id, { professeur_id: Number(ajoute), role });
        const nom = candidats.find((p) => p.value === ajoute)?.label;
        onDone(`${nom} a été ajouté à la ${titreSession}. Les autres sessions et les heures déjà encodées ne changent pas.`, res.avertissements || []);
      });
    }
    return executer(async () => {
      const res = await remplacerProfesseur(session.id, {
        professeur_remplace_id: Number(remplace),
        professeur_remplacant_id: Number(remplacant),
      });
      const nomA = remplacables.find((l) => String(l.professeur_id) === remplace)?.professeur?.nom;
      const nomB = candidats.find((p) => p.value === remplacant)?.label;
      onDone(`${nomB} remplace ${nomA} sur la ${titreSession}. Les autres sessions et les heures déjà encodées ne changent pas.`, res.avertissements || []);
    });
  }

  function retirer(ligne) {
    return executer(async () => {
      await retirerProfesseurSession(session.id, ligne.professeur_id);
      onDone(`${ligne.professeur.nom} a été retiré de la ${titreSession}.`);
    });
  }

  function annuler(ligne) {
    return executer(async () => {
      await annulerRemplacement(session.id, ligne.professeur_id);
      onDone(`Remplacement annulé : ${ligne.professeur.nom} est de nouveau assigné à la ${titreSession}.`);
    });
  }

  const pret = onglet === 'ajouter' ? Boolean(ajoute) : Boolean(remplace && remplacant);

  return (
    <AdminModal
      isOpen
      title={`Professeurs — ${libelleSession(session)}, ${formatDate(session.date)}`}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Fermer sans modifier
          </AdminButton>
          <AdminButton type="submit" form="form-professeurs-session" disabled={!pret || envoi} loading={envoi}>
            {onglet === 'ajouter' ? 'Ajouter à cette session' : 'Remplacer sur cette session'}
          </AdminButton>
        </>
      }
    >
      <form id="form-professeurs-session" onSubmit={soumettre} noValidate>
        {message && (
          <Banner tone="error" role="alert">
            <strong>{message}</strong>
          </Banner>
        )}
        {passee && (
          <Banner tone="info">
            Cette séance est passée. Les heures déjà encodées ne changent pas ; le professeur ajouté ou remplaçant encode les siennes.
          </Banner>
        )}

        <strong style={{ fontSize: '13px' }}>Sur cette séance</strong>
        {lignes === null ? (
          !message && <p style={{ color: ADMIN_COLORS.textSecondary, fontSize: '13px' }}>Chargement…</p>
        ) : lignes.length === 0 ? (
          <p style={{ color: ADMIN_COLORS.textSecondary, fontSize: '13px' }}>Aucun professeur sur cette séance.</p>
        ) : (
          <ul style={{ listStyle: 'none', margin: `${ADMIN_SPACING.sm} 0 ${ADMIN_SPACING.xl}`, padding: 0 }}>
            {lignes.map((l) => (
              <li key={l.id} style={{ display: 'flex', alignItems: 'center', gap: ADMIN_SPACING.md, padding: `${ADMIN_SPACING.xs} 0`, flexWrap: 'wrap' }}>
                <span style={{ textDecoration: l.remplace ? 'line-through' : undefined }}>{l.professeur.nom}</span>
                <StatutBadge table={ROLES_PROFESSEUR} valeur={l.role} />
                {l.origine !== 'classe' && <StatutBadge table={ORIGINES_SESSION} valeur={l.origine} />}
                {l.remplace && (l.remplace_par_professeur_id
                  ? <span style={{ fontSize: '13px' }}>remplacé par {l.remplace_par?.nom}</span>
                  : <StatutBadge label="Absent — remplaçant à trouver" tone="warning" />)}
                {l.remplace && (
                  <AdminButton size="sm" variant="secondary" disabled={envoi} onClick={() => annuler(l)}>
                    Annuler ce remplacement
                  </AdminButton>
                )}
                {l.origine === 'ajout' && !l.remplace && (
                  <AdminButton size="sm" variant="secondary" disabled={envoi} onClick={() => retirer(l)}>
                    Retirer
                  </AdminButton>
                )}
              </li>
            ))}
          </ul>
        )}

        <div role="tablist" aria-label="Action" style={{ display: 'flex', gap: ADMIN_SPACING.md, margin: `${ADMIN_SPACING.md} 0` }}>
          {[['ajouter', 'Ajouter'], ['remplacer', 'Remplacer']].map(([id, libelle]) => (
            <AdminButton key={id} role="tab" aria-selected={onglet === id} size="sm" variant={onglet === id ? 'primary' : 'secondary'} onClick={() => setOnglet(id)}>
              {libelle}
            </AdminButton>
          ))}
        </div>

        {onglet === 'ajouter' ? (
          <>
            <AdminFormField label="Professeur à ajouter" htmlFor="ajout-professeur" required error={erreurs.professeur_id}>
              <AdminSelect id="ajout-professeur" value={ajoute} placeholder="Choisir un professeur" options={candidats} onChange={(e) => setAjoute(e.target.value)} />
            </AdminFormField>
            <AdminFormField label="Rôle (indicatif)" htmlFor="ajout-role" error={erreurs.role}>
              <AdminSelect
                id="ajout-role"
                value={role}
                options={Object.entries(ROLES_PROFESSEUR).map(([value, r]) => ({ value, label: r.label }))}
                onChange={(e) => setRole(e.target.value)}
              />
            </AdminFormField>
            <p style={{ color: ADMIN_COLORS.textSecondary, fontSize: '13px', margin: 0 }}>
              Seule la <strong>{titreSession}</strong> est concernée : le professeur n'est pas assigné à la classe. Le rôle n'a aucun effet sur la
              rémunération.
            </p>
          </>
        ) : (
          <>
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
              <AdminSelect id="remp-remplacant" value={remplacant} placeholder="Choisir le remplaçant" options={candidats} onChange={(e) => setRemplacant(e.target.value)} />
            </AdminFormField>
            <p style={{ color: ADMIN_COLORS.textSecondary, fontSize: '13px', margin: 0 }}>
              Seule la <strong>{titreSession}</strong> est concernée. Les heures déjà encodées par le professeur remplacé restent inchangées ; le
              remplaçant encode les siennes, de façon indépendante.
            </p>
          </>
        )}
      </form>
    </AdminModal>
  );
}
