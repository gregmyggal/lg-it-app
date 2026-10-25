import { useEffect, useMemo, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import StatutBadge from '../ui/StatutBadge';
import { LoadingBlock } from '../ui/DataStates';
import { useProfesseursListe } from '../../hooks/useProfesseursClasses';
import { apercuRemplacementLot, appliquerRemplacementLot, chargerSeancesProfesseur } from '../../hooks/useDirection';
import { getErrorMessage } from '../../api/errors';
import { formatDateCourte, formatHoraire } from '../../utils/dates';

const jour = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const PERIODES = [
  { id: 'aujourdhui', label: "Aujourd'hui", jours: 0 },
  { id: 'semaine', label: 'Cette semaine', jours: 6 },
  { id: 'mois', label: '30 jours', jours: 29 },
];

/**
 * Remplacer un professeur sur plusieurs séances en un bloc ATOMIQUE (UI-M6) : séances cochées, remplaçant, aperçu des
 * conflits, puis « Remplacer sur N séances ». Si une séance est refusée par le serveur, aucune n'est modifiée.
 */
export default function RemplacerPlusieursModal({ professeur, onClose, onDone }) {
  const profs = useProfesseursListe();
  const [periode, setPeriode] = useState('semaine');
  const [seances, setSeances] = useState(null);
  const [choisies, setChoisies] = useState(new Set());
  const [remplacantId, setRemplacantId] = useState('');
  const [apercu, setApercu] = useState(null);
  const [erreur, setErreur] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  useEffect(() => {
    const p = PERIODES.find((x) => x.id === periode);
    const du = new Date();
    const au = new Date();
    au.setDate(au.getDate() + p.jours);
    let annule = false;
    setSeances(null);
    chargerSeancesProfesseur(professeur.id, jour(du), jour(au))
      .then((liste) => {
        if (annule) return;
        setSeances(liste);
        setChoisies(new Set(liste.map((s) => s.id)));
      })
      .catch((err) => !annule && setErreur(getErrorMessage(err)));
    return () => {
      annule = true;
    };
  }, [periode, professeur.id]);

  const ids = useMemo(() => [...choisies].sort((a, b) => a - b), [choisies]);

  // Aperçu des conflits dès que les séances et le remplaçant sont choisis : aucune écriture.
  useEffect(() => {
    setApercu(null);
    if (!remplacantId || ids.length === 0) return undefined;
    let annule = false;
    apercuRemplacementLot(professeur.id, ids, Number(remplacantId))
      .then((r) => !annule && (setApercu(r), setErreur(null)))
      .catch((err) => !annule && setErreur(getErrorMessage(err)));
    return () => {
      annule = true;
    };
  }, [remplacantId, ids, professeur.id]);

  const basculer = (id) =>
    setChoisies((prev) => {
      const n = new Set(prev);
      if (n.has(id)) n.delete(id);
      else n.add(id);
      return n;
    });

  async function appliquer() {
    setEnvoi(true);
    setErreur(null);
    try {
      const r = await appliquerRemplacementLot(professeur.id, ids, Number(remplacantId));
      onDone(`${r.seances} séance${r.seances > 1 ? 's' : ''} remplacée${r.seances > 1 ? 's' : ''}. Le remplaçant est prévenu par mail.`);
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  const candidats = (profs.data || []).filter((p) => p.id !== professeur.id && p.statut !== 'inactif');
  const nom = `${professeur.prenom || ''} ${professeur.nom || ''}`.trim();

  return (
    <AdminModal
      isOpen
      title={`Remplacer ${nom}`}
      size="md"
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton loading={envoi} disabled={!remplacantId || ids.length === 0} onClick={appliquer}>
            {ids.length > 0 ? `Remplacer sur ${ids.length} séance${ids.length > 1 ? 's' : ''}` : 'Remplacer'}
          </AdminButton>
        </>
      }
    >
      <p style={{ margin: '0 0 12px', fontSize: 13, color: 'var(--c-text-2)' }}>Tout ou rien : si une séance échoue, aucune n'est modifiée. Les heures déjà encodées ne bougent pas.</p>
      {erreur && (
        <Banner tone="error" role="alert">
          <strong>{erreur}</strong>
        </Banner>
      )}

      <div className="pm-puces" role="group" aria-label="Période" style={{ flexWrap: 'wrap', overflow: 'visible' }}>
        {PERIODES.map((p) => (
          <button key={p.id} type="button" className={`pm-puce${periode === p.id ? ' pm-puce--on' : ''}`} onClick={() => setPeriode(p.id)}>
            {p.label}
          </button>
        ))}
      </div>

      <h3 className="choix-prof__titre">Séances à couvrir</h3>
      {!seances && !erreur && <LoadingBlock message="Chargement des séances…" lignes={3} />}
      {seances && seances.length === 0 && <p>Aucune séance sur cette période.</p>}
      {seances && seances.length > 0 && (
        <ul className="choix-prof__liste">
          {seances.map((s) => (
            <li key={s.id}>
              <label className="choix-prof" style={{ cursor: 'pointer' }}>
                <input type="checkbox" checked={choisies.has(s.id)} onChange={() => basculer(s.id)} aria-label={`${s.classe_libelle} ${formatDateCourte(s.date)}`} />
                <span className="choix-prof__texte">
                  <strong>
                    {s.classe_libelle} · {s.libelle_complet}
                  </strong>
                  <small>
                    {formatDateCourte(s.date)} · {formatHoraire(s.heure_debut, s.heure_fin)}
                    {s.heures_encodees && <StatutBadge label="Heures déjà encodées" tone="warning" />}
                  </small>
                </span>
              </label>
            </li>
          ))}
        </ul>
      )}

      <h3 className="choix-prof__titre">Remplaçant</h3>
      <select aria-label="Remplaçant" value={remplacantId} onChange={(e) => setRemplacantId(e.target.value)} style={{ width: '100%' }}>
        <option value="">Choisir un professeur…</option>
        {candidats.map((p) => (
          <option key={p.id} value={p.id}>
            {p.nom}
          </option>
        ))}
      </select>

      {apercu && apercu.avertissements.length > 0 && (
        <Banner tone="warning">
          <strong>
            {apercu.avertissements.length} conflit{apercu.avertissements.length > 1 ? 's' : ''} d'horaire
          </strong>{' '}
          : le remplaçant a déjà {apercu.avertissements.map((c) => `« ${c.classe} » le ${formatDateCourte(c.date)} (${c.heure_debut}–${c.heure_fin})`).join(', ')}. Vous pouvez continuer.
        </Banner>
      )}
    </AdminModal>
  );
}
