import { useMemo, useState } from 'react';
import AdminButton from '../AdminButton';
import AdminModal from '../AdminModal';
import { AdminFormField, AdminTextarea } from '../AdminFormField';
import Banner from '../ui/Banner';
import { LoadingBlock } from '../ui/DataStates';
import EmployeurBadge from './EmployeurBadge';
import { definirEmployeurLot, definirEmployeurMois, useEmployeurs } from '../../hooks/useEmployeurs';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const REPRENDRE = 'reprendre';

/**
 * « Définir l'employeur du mois » (EMP-01, mock-up B/C) : pour un animateur (PUT avec version) ou plusieurs (lot).
 * Les mois verrouillés sont listés et seront ignorés. Le serveur reste l'autorité (verrous, motif, version, droits).
 *
 * @param {object} props
 * @param {number} props.annee
 * @param {number} props.mois 1-12
 * @param {Array<{professeur_id: number, professeur: string, vue: object}>} props.cibles `vue` = employeur du mois renvoyé par l'API
 * @param {'choisir'|'reprendre'} [props.modeInitial]
 * @param {() => void} props.onClose
 * @param {(resultat: {message: string, ignores: object[]}) => void} props.onDone
 */
export default function DefinirEmployeurModal({ annee, mois, cibles, modeInitial = 'choisir', onClose, onDone }) {
  const entites = useEmployeurs();
  const actives = useMemo(() => (entites.data || []).filter((e) => e.actif), [entites.data]);
  const [choix, setChoix] = useState(modeInitial === 'reprendre' ? REPRENDRE : null);
  const [motif, setMotif] = useState('');
  const [envoi, setEnvoi] = useState(false);
  const [erreurs, setErreurs] = useState({});
  const [erreurGlobale, setErreurGlobale] = useState(null);

  const modifiables = cibles.filter((c) => c.vue.modifiable);
  const verrouilles = cibles.filter((c) => !c.vue.modifiable);
  const unique = cibles.length === 1;
  // Plusieurs mois d'un même animateur (frise) : chaque cible porte son `mois` et est enregistrée avec sa version.
  const multiMois = cibles.some((c) => c.mois);
  const libelleMois = multiMois
    ? `${cibles.length} mois de ${annee}`
    : new Date(annee, mois - 1, 1).toLocaleDateString('fr-BE', { month: 'long', year: 'numeric' });

  // Pré-rempli : l'entité en vigueur du premier animateur modifiable (le cas courant est « pas de changement »).
  const valeur = choix ?? modifiables[0]?.vue.employeur.id ?? actives[0]?.id ?? null;
  const reprise = valeur === REPRENDRE;
  const entite = actives.find((e) => e.id === valeur);
  const invalideSignature = modifiables.some((c) => c.vue.type_verrou === 'signature');
  const aDejaUneValeur = modifiables.some((c) => ['explicite', 'migration', 'fige'].includes(c.vue.source));

  async function appliquer() {
    setEnvoi(true);
    setErreurs({});
    setErreurGlobale(null);
    try {
      if (multiMois) {
        const ignores = [];
        let n = 0;
        for (const c of modifiables) {
          try {
            await definirEmployeurMois(c.professeur_id, annee, c.mois, { employeur_id: valeur, motif: motif.trim() || null, version: c.vue.version });
            n += 1;
          } catch (err) {
            ignores.push({ professeur_id: c.professeur_id, professeur: c.professeur, raison: getErrorMessage(err) });
          }
        }
        if (n === 0) throw Object.assign(new Error('aucun'), { response: { status: 422, data: { message: ignores[0]?.raison || 'Enregistrement impossible', errors: {} } } });
        onDone({ message: `Employeur mis à jour pour ${n} mois${ignores.length ? `, ${ignores.length} ignoré(s)` : ''}.`, ignores });
      } else if (unique && !reprise) {
        const c = cibles[0];
        await definirEmployeurMois(c.professeur_id, annee, mois, { employeur_id: valeur, motif: motif.trim() || null, version: c.vue.version });
        onDone({ message: `Employeur mis à jour : ${entite?.nom}.`, ignores: [] });
      } else {
        const payload = { annee, mois, professeur_ids: modifiables.map((c) => c.professeur_id), motif: motif.trim() || null };
        const res = await definirEmployeurLot(reprise ? { ...payload, reprendre_precedent: true } : { ...payload, employeur_id: valeur });
        const n = res.appliques.length;
        const ign = res.ignores.length + verrouilles.length;
        onDone({
          message: `Employeur mis à jour pour ${n} animateur(s)${ign ? `, ${ign} ignoré(s)` : ''}.`,
          ignores: [...res.ignores, ...verrouilles.map((c) => ({ professeur_id: c.professeur_id, professeur: c.professeur, raison: c.vue.raison_verrou }))],
        });
      }
    } catch (err) {
      setErreurs(getFieldErrors(err));
      setErreurGlobale(getErrorMessage(err, 'Enregistrement impossible'));
    } finally {
      setEnvoi(false);
    }
  }

  const titre = reprise ? 'Reprendre le mois précédent' : multiMois ? 'Définir l’employeur de plusieurs mois' : 'Définir l’employeur du mois';
  const action = reprise
    ? `Reprendre le mois précédent (${modifiables.length})`
    : unique || multiMois ? 'Enregistrer' : `Appliquer à ${modifiables.length} animateur(s)`;

  return (
    <AdminModal
      isOpen
      title={`${titre} — ${libelleMois}`}
      size="md"
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton loading={envoi} disabled={modifiables.length === 0 || valeur === null || entites.loading} onClick={appliquer}>{action}</AdminButton>
        </>
      }
    >
      {erreurGlobale && <Banner tone="error" role="alert">{erreurGlobale}</Banner>}
      {entites.loading && <LoadingBlock message="Chargement des entités…" />}
      {entites.error && <Banner tone="error">Impossible de charger les employeurs. Fermez puis rouvrez cette fenêtre pour réessayer.</Banner>}

      <p style={{ margin: `0 0 ${ADMIN_SPACING.sm}`, fontSize: 13 }}>
        <strong>{multiMois ? 'Mois' : `Animateur${cibles.length > 1 ? `s (${cibles.length})` : ''}`}</strong> : {cibles.map((c) => c.professeur).join(', ')}
      </p>
      {verrouilles.length > 0 && (
        <Banner tone="warning">
          {verrouilles.map((c) => `${c.professeur} : ${c.vue.raison_verrou}`).join(' · ')} {cibles.length > 1 ? '— sera ignoré.' : ''}
        </Banner>
      )}

      {modifiables.length > 0 && (
        <>
          <fieldset style={{ border: 0, padding: 0, margin: `0 0 ${ADMIN_SPACING.lg}` }}>
            <legend style={{ fontSize: 12, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: ADMIN_SPACING.sm }}>Employeur</legend>
            {actives.map((e) => (
              <label key={e.id} style={{ display: 'flex', alignItems: 'center', gap: ADMIN_SPACING.md, padding: ADMIN_SPACING.md, border: `1px solid ${valeur === e.id ? ADMIN_COLORS.primary : ADMIN_COLORS.border}`, borderRadius: ADMIN_RADIUS.md, marginBottom: ADMIN_SPACING.sm, cursor: 'pointer' }}>
                <input type="radio" name="employeur" checked={valeur === e.id} onChange={() => setChoix(e.id)} />
                <EmployeurBadge employeur={e} source="explicite" />
                {!e.coordonnees_completes && <span style={{ fontSize: 12, color: 'var(--tone-warning-fg)' }}>coordonnées à compléter : aucune fiche PDF ne pourra être générée</span>}
              </label>
            ))}
            {!multiMois ? (
              <label style={{ display: 'flex', alignItems: 'center', gap: ADMIN_SPACING.md, padding: ADMIN_SPACING.md, border: `1px solid ${reprise ? ADMIN_COLORS.primary : ADMIN_COLORS.border}`, borderRadius: ADMIN_RADIUS.md, cursor: 'pointer' }}>
                <input type="radio" name="employeur" checked={reprise} onChange={() => setChoix(REPRENDRE)} />
                Reprendre le mois précédent (l’entité du mois d’avant est reconduite)
              </label>
            ) : null}
          </fieldset>

          <AdminFormField
            label={aDejaUneValeur ? 'Motif (obligatoire pour une modification)' : 'Motif (obligatoire si une valeur est remplacée ou si le mois est passé)'}
            htmlFor="employeur-motif"
            error={erreurs.motif}
          >
            <AdminTextarea id="employeur-motif" rows={2} value={motif} onChange={(e) => setMotif(e.target.value)} placeholder="Ex. Passage sous contrat L-IT à partir de ce mois" />
          </AdminFormField>

          <div style={{ fontSize: 13, color: 'var(--c-text-2)' }}>
            <strong>Conséquences</strong>
            <ul style={{ margin: `${ADMIN_SPACING.xs} 0 0`, paddingLeft: 18 }}>
              <li>Les fiches de {libelleMois} seront émises au nom de {reprise ? 'l’entité reprise' : `« ${entite?.nom || '…'} »`} (nom, RPM, compte).</li>
              <li>Cette entité sera le responsable de traitement des heures du mois.</li>
              <li>Le changement est journalisé (qui, quand, motif).</li>
              {invalideSignature && <li><strong>Modifier l’employeur invalidera la signature : l’animateur devra signer à nouveau.</strong></li>}
            </ul>
          </div>
        </>
      )}
    </AdminModal>
  );
}
