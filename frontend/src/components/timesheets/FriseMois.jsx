import { useVersionsMois } from '../../hooks/useTimesheets';
import { formatDateCourte, formatDateHeure } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';
import { ADMIN_COLORS } from '../../styles/AdminDesignSystem';

const ACTIONS_AUDIT = ['soumission_forcee', 'adaptation', 'lissage', 'remise_brouillon', 'contestation', 'reponse_contestation', 'classe_supprimee'];

/**
 * TS-04 : frise chronologique d'un mois — soumissions, adaptations, remises en brouillon, validations, signature et
 * PDF, de la plus ancienne à la plus récente. Les versions viennent de l'API ; le reste du détail du mois.
 *
 * @param {object} props
 * @param {number} props.professeurId
 * @param {number} props.annee
 * @param {number} props.mois
 * @param {object[]} props.historique traces d'audit du mois (détail du mois)
 * @param {object|null} props.notes notes internes du mois (direction/admin) : seul le fait d'en avoir ajouté une est affiché
 * @param {object[]} [props.lignes] saisies du mois (libellé de la ligne d'une note)
 * @param {object|null} props.signature signature en vigueur (détail du mois)
 * @param {{id: number, version: number, total_eur: number, generated_at: string}[]} props.pdfs fiches PDF générées
 * @param {(h: object) => string} props.texteAudit libellé d'une trace d'audit
 * @param {*} props.rafraichir change à chaque rechargement du détail du mois
 */
export default function FriseMois({ professeurId, annee, mois, historique, signature, pdfs, notes, lignes = [], texteAudit, rafraichir }) {
  const versions = useVersionsMois(professeurId, annee, mois, { rafraichir });
  const evenements = [];

  (versions.data?.historique.soumises || []).forEach((v) => evenements.push({
    date: v.created_at, cle: `s${v.numero}`,
    titre: `Soumise · v${v.numero}`, detail: `${formatEuros(v.total_eur)} · ${formatHeures(v.total_heures)}${v.statut === 'remplacee' ? ' · remplacée' : ''}`,
  }));
  (versions.data?.historique.validees || []).forEach((v) => evenements.push({
    date: v.created_at, cle: `v${v.numero}`,
    titre: `Validée · v${v.numero}`, detail: `${formatEuros(v.total_eur)} · ${formatHeures(v.total_heures)}${v.statut === 'remplacee' ? ' · remplacée' : ''}`,
  }));
  // Une soumission forcée laisse une trace par saisie : un seul événement dans la frise.
  const vus = new Set();
  historique.filter((h) => ACTIONS_AUDIT.includes(h.action)).filter((h) => {
    if (h.action !== 'soumission_forcee') return true;
    if (vus.has(h.created_at)) return false;
    vus.add(h.created_at);
    return true;
  }).forEach((h) => evenements.push(h.action === 'soumission_forcee' ? {
    date: h.created_at, cle: `a${h.id}`,
    titre: 'Soumis par la direction', detail: `${h.auteur || 'Direction'} — « ${h.motif} »`,
  } : {
    date: h.created_at, cle: `a${h.id}`,
    titre: `${h.auteur || 'Système'} · saisie du ${formatDateCourte(h.date_prestation)}`, detail: `${texteAudit(h)} — « ${h.motif} »`,
  }));
  // TS-06 : une note interne ajoutée apparaît dans la frise (écran direction/admin uniquement), sans son texte.
  const notesListe = [...(notes?.mois ? [notes.mois] : []), ...Object.entries(notes?.lignes || {}).map(([id, n]) => ({ ...n, ligneId: Number(id) }))];
  notesListe.forEach((n) => {
    const ligne = n.ligneId ? lignes.find((l) => l.id === n.ligneId) : null;
    evenements.push({
      date: n.created_at, cle: `n${n.id}`,
      titre: '🔒 Note interne ajoutée', detail: `${n.auteur?.nom || 'Direction'}${ligne ? ` — ligne du ${formatDateCourte(ligne.date_prestation)}` : ' — mois'}`,
    });
  });
  if (signature?.signed_at) evenements.push({ date: signature.signed_at, cle: 'sig', titre: 'Signée par le professeur', detail: signature.perimee ? 'signature périmée : le contenu a changé depuis' : null });
  (pdfs || []).forEach((p) => evenements.push({ date: p.generated_at, cle: `p${p.id}`, titre: `Fiche PDF v${p.version} générée`, detail: formatEuros(p.total_eur) }));

  evenements.sort((a, b) => new Date(a.date) - new Date(b.date));

  if (versions.loading && !versions.data) return <p style={{ color: ADMIN_COLORS.textSecondary }}>Chargement de l'historique…</p>;
  if (evenements.length === 0) return <p style={{ color: 'var(--c-text-2)' }}>Aucun événement enregistré pour ce mois.</p>;

  return (
    <>
      {versions.error && <p role="alert" style={{ fontSize: 13 }}>Les versions n'ont pas pu être chargées : seuls les autres événements sont affichés.</p>}
      <ol style={{ listStyle: 'none', margin: 0, padding: 0, fontSize: 13 }}>
        {evenements.map((e) => (
          <li key={e.cle} style={{ borderLeft: `2px solid ${ADMIN_COLORS.border}`, paddingLeft: 12, paddingBottom: 12 }}>
            <strong>{e.titre}</strong>
            <div style={{ color: ADMIN_COLORS.textSecondary }}>{formatDateHeure(e.date)}</div>
            {e.detail && <div>{e.detail}</div>}
          </li>
        ))}
      </ol>
    </>
  );
}
