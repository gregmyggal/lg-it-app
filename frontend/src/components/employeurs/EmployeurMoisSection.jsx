import { useState } from 'react';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import { LoadingBlock, ErrorBlock } from '../ui/DataStates';
import { Table, Th, Td, Tr } from '../ui/Table';
import EmployeurBadge from './EmployeurBadge';
import DefinirEmployeurModal from './DefinirEmployeurModal';
import { useEmployeursMois, useHistoriqueEmployeurs } from '../../hooks/useEmployeurs';
import { useToast } from '../../hooks/useToast';
import { NOMS_MOIS_COURTS } from '../../utils/employeurs';
import { formatDateHeure } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

/**
 * Employeur par mois d'un animateur (EMP-01, mock-up C) : frise des 12 mois (clic = éditer ; Maj+clic = sélectionner
 * plusieurs mois) et historique des changements. Staff uniquement ; le serveur décide des droits et des verrous.
 *
 * @param {object} props
 * @param {number} props.professeurId
 * @param {string} props.professeurNom
 * @param {number} [props.anneeInitiale]
 * @param {() => void} [props.onChange] rechargement des vues parentes après un changement
 */
export default function EmployeurMoisSection({ professeurId, professeurNom, anneeInitiale, onChange }) {
  const toast = useToast();
  const [annee, setAnnee] = useState(anneeInitiale || new Date().getFullYear());
  const frise = useEmployeursMois(professeurId, annee);
  const historique = useHistoriqueEmployeurs(professeurId, annee);
  const [selection, setSelection] = useState(new Set());
  const [edition, setEdition] = useState(null); // liste de numéros de mois à éditer
  const [recap, setRecap] = useState(null);

  const mois = frise.data?.mois || [];
  const parNumero = new Map(mois.map((m) => [m.mois, m]));

  function cliquer(m, e) {
    if (e.shiftKey || e.ctrlKey || e.metaKey) {
      setSelection((prev) => {
        const n = new Set(prev);
        if (n.has(m.mois)) n.delete(m.mois);
        else n.add(m.mois);
        return n;
      });
      return;
    }
    setSelection(new Set());
    setEdition([m.mois]);
  }

  const cibles = (edition || []).map((n) => ({
    professeur_id: professeurId,
    professeur: edition.length > 1 ? `${NOMS_MOIS_COURTS[n - 1]} ${annee}` : professeurNom,
    ...(edition.length > 1 ? { mois: n } : {}),
    vue: parNumero.get(n),
  }));

  function apres({ message, ignores }) {
    setEdition(null);
    setSelection(new Set());
    setRecap(ignores.length ? ignores : null);
    toast.success(message);
    frise.reload();
    historique.reload();
    onChange?.();
  }

  return (
    <section aria-label="Employeur par mois" style={{ border: '1px solid var(--c-border)', borderRadius: 8, background: 'var(--c-card)', padding: 16, marginBottom: ADMIN_SPACING.lg }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: ADMIN_SPACING.md }}>
        <h3 style={{ margin: 0 }}>Employeur par mois</h3>
        <div style={{ display: 'flex', alignItems: 'center', gap: ADMIN_SPACING.md }}>
          <AdminButton variant="secondary" size="sm" onClick={() => setAnnee(annee - 1)} aria-label="Année précédente">‹</AdminButton>
          <strong aria-live="polite">{annee}</strong>
          <AdminButton variant="secondary" size="sm" onClick={() => setAnnee(annee + 1)} aria-label="Année suivante">›</AdminButton>
        </div>
      </div>
      <p style={{ fontSize: 12, color: 'var(--c-text-2)', margin: `${ADMIN_SPACING.sm} 0` }}>
        Clic sur un mois pour l’éditer ; Maj+clic pour en sélectionner plusieurs. « Hérité » = repris du mois précédent, pas encore confirmé.
      </p>

      {frise.loading && !frise.data && <LoadingBlock message="Chargement des employeurs…" lignes={2} />}
      {frise.error && <ErrorBlock message="Impossible de charger les employeurs." onRetry={frise.reload} />}
      {recap && (
        <Banner tone="warning">
          Ignoré(s) : {recap.map((r) => `${r.professeur} (${r.raison})`).join(' · ')}
        </Banner>
      )}

      {frise.data && (
        <>
          {mois.every((m) => m.source === 'defaut') && (
            <p style={{ fontSize: 13, margin: `0 0 ${ADMIN_SPACING.sm}` }}>Aucun mois défini : l’ASBL est appliquée par défaut.</p>
          )}
          <div role="group" aria-label={`Employeur de chaque mois de ${annee}`} style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(104px, 1fr))', gap: ADMIN_SPACING.sm }}>
            {mois.map((m) => (
              <button
                key={m.mois}
                type="button"
                onClick={(e) => cliquer(m, e)}
                aria-pressed={selection.has(m.mois)}
                title={m.raison_verrou || undefined}
                style={{
                  textAlign: 'left', cursor: 'pointer', padding: ADMIN_SPACING.sm, background: 'var(--c-card)', color: 'inherit', font: 'inherit',
                  border: `2px solid ${selection.has(m.mois) ? ADMIN_COLORS.primary : ADMIN_COLORS.border}`, borderRadius: ADMIN_RADIUS.md,
                }}
              >
                <div style={{ fontSize: 12, fontWeight: 600, marginBottom: 4 }}>{NOMS_MOIS_COURTS[m.mois - 1]}</div>
                <EmployeurBadge employeur={m.employeur} source={m.source} verrouille={m.verrouille} />
              </button>
            ))}
          </div>
          <div style={{ marginTop: ADMIN_SPACING.md, display: 'flex', gap: ADMIN_SPACING.sm, alignItems: 'center', flexWrap: 'wrap' }}>
            <AdminButton size="sm" disabled={selection.size === 0} onClick={() => setEdition([...selection].sort((a, b) => a - b))}>
              Définir pour {selection.size || ''} mois sélectionné(s)
            </AdminButton>
            {selection.size > 0 && <AdminButton size="sm" variant="secondary" onClick={() => setSelection(new Set())}>Annuler la sélection</AdminButton>}
          </div>
        </>
      )}

      <h4 style={{ margin: `${ADMIN_SPACING.lg} 0 ${ADMIN_SPACING.sm}` }}>Historique {annee}</h4>
      {historique.loading && !historique.data && <LoadingBlock message="Chargement de l’historique…" lignes={2} />}
      {historique.error && <ErrorBlock message="Impossible de charger l’historique." onRetry={historique.reload} />}
      {historique.data && (historique.data.length === 0 ? (
        <p style={{ fontSize: 13, color: 'var(--c-text-2)', margin: 0 }}>Aucun changement enregistré en {annee}.</p>
      ) : (
        <Table caption={`Historique des changements d’employeur en ${annee}`} minWidth="560px" cards>
          <thead><tr><Th>Date</Th><Th>Mois</Th><Th>Changement</Th><Th>Par</Th><Th>Motif</Th></tr></thead>
          <tbody>
            {historique.data.map((h) => (
              <Tr key={h.id}>
                <Td label="Date">{formatDateHeure(h.created_at)}</Td>
                <Td label="Mois">{NOMS_MOIS_COURTS[h.mois - 1]} {h.annee}</Td>
                <Td label="Changement">{h.employeur_avant ? `${h.employeur_avant.nom} → ` : '→ '}{h.employeur_apres.nom}</Td>
                <Td label="Par">{h.auteur}</Td>
                <Td label="Motif">{h.motif || '—'}</Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      ))}

      {edition && frise.data && (
        <DefinirEmployeurModal annee={annee} mois={edition[0]} cibles={cibles} onClose={() => setEdition(null)} onDone={apres} />
      )}
    </section>
  );
}
