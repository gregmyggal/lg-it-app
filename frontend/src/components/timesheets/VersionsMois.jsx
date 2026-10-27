import { useState } from 'react';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import StatutBadge from '../ui/StatutBadge';
import { Table, Th, Td, Tr } from '../ui/Table';
import { LoadingBlock, ErrorBlock } from '../ui/DataStates';
import { useVersionsMois } from '../../hooks/useTimesheets';
import { useMediaQuery } from '../../hooks/useMediaQuery';
import { TYPES_ACTIVITE } from '../../utils/statuts';
import { formatDateCourte, formatDateHeure } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const ETATS = {
  identique: { label: 'Identique', fond: undefined },
  modifiee: { label: 'Modifiée', fond: 'var(--tone-warning-bg)' },
  ajoutee: { label: 'Ajoutée', fond: 'var(--tone-success-bg)' },
  retiree: { label: 'Retirée', fond: 'var(--tone-error-bg)' },
};

const signe = (n) => (n > 0 ? '+' : n < 0 ? '−' : '');
const valeur = (l) => (l ? `${formatHeures(l.heures)} · ${formatEuros(l.montant)}` : '—');
const objet = (l) => `${l.libelle} · ${TYPES_ACTIVITE[l.type]?.label || l.type}`;

function Vue({ children, label }) {
  return <section aria-label={label}>{children}</section>;
}

export function TableVersion({ version, titre }) {
  if (version.detail_efface) {
    return (
      <Banner tone="info">
        Détail effacé après 24 mois. Totaux et empreinte conservés : {formatEuros(version.total_eur)} · {formatHeures(version.total_heures)}
        {version.empreinte ? ` · empreinte ${version.empreinte.slice(0, 8)}…` : ''}.
      </Banner>
    );
  }
  return (
    <Table caption={titre} minWidth="560px" cards>
      <thead><tr><Th>Date</Th><Th>Session / type</Th><Th>Heures</Th><Th>Montant</Th></tr></thead>
      <tbody>
        {version.lignes.map((l) => (
          <Tr key={l.id}>
            <Td label="Date">{formatDateCourte(l.date)}</Td>
            <Td label="Session / type">{objet(l)}{l.origine_id ? ' (lissée)' : ''}</Td>
            <Td label="Heures">{formatHeures(l.heures)}</Td>
            <Td label="Montant">{formatEuros(l.montant)}</Td>
          </Tr>
        ))}
      </tbody>
      <tfoot><tr><Td label="Total" colSpan={2}><strong>Total</strong></Td><Td label="Heures"><strong>{formatHeures(version.total_heures)}</strong></Td><Td label="Montant"><strong>{formatEuros(version.total_eur)}</strong></Td></tr></tfoot>
    </Table>
  );
}

export function TableEcarts({ ecart }) {
  return (
    <Table caption="Écarts entre la version soumise et la version validée" minWidth="720px" cards>
      <thead><tr><Th>Date</Th><Th>Session / type</Th><Th>Soumise</Th><Th>Validée</Th><Th>Écart</Th><Th>Motif</Th></tr></thead>
      <tbody>
        {ecart.lignes.map((l) => {
          const ref = l.apres || l.avant;
          return (
            <Tr key={l.id} fond={ETATS[l.etat].fond}>
              <Td label="Date">{formatDateCourte(ref.date)}</Td>
              <Td label="Session / type">{objet(ref)}{l.etat === 'ajoutee' && l.apres.origine_id ? ' (lissée)' : ''}</Td>
              <Td label="Soumise">{l.avant ? <>{l.etat === 'modifiee' && <span className="sr-only">avant : </span>}{valeur(l.avant)}</> : '—'}</Td>
              <Td label="Validée">{valeur(l.apres)}</Td>
              <Td label="Écart">
                {l.etat === 'identique' ? '—' : <><strong>{ETATS[l.etat].label}</strong> · {signe(l.ecart_heures)}{formatHeures(Math.abs(l.ecart_heures))} · {signe(l.ecart_eur)}{formatEuros(Math.abs(l.ecart_eur))}</>}
              </Td>
              <Td label="Motif">{l.motif ? <>« {l.motif} »{l.auteur ? <span style={{ display: 'block', fontSize: 12, color: ADMIN_COLORS.textSecondary }}>{l.auteur}</span> : null}</> : ''}</Td>
            </Tr>
          );
        })}
      </tbody>
      <tfoot>
        <tr>
          <Td label="Total" colSpan={2}><strong>Total du mois</strong></Td>
          <Td label="Soumise"><strong>{formatHeures(ecart.total_avant.heures)} · {formatEuros(ecart.total_avant.eur)}</strong></Td>
          <Td label="Validée"><strong>{formatHeures(ecart.total_apres.heures)} · {formatEuros(ecart.total_apres.eur)}</strong></Td>
          <Td label="Écart" colSpan={2}><strong>{signe(ecart.ecart_heures)}{formatHeures(Math.abs(ecart.ecart_heures))} · {signe(ecart.ecart_eur)}{formatEuros(Math.abs(ecart.ecart_eur))}</strong></Td>
        </tr>
      </tfoot>
    </Table>
  );
}

export function CoteACote({ soumise, validee, ecart, titreSoumise = 'Soumise', titreValidee = 'Validée' }) {
  const etat = Object.fromEntries((ecart?.lignes || []).map((l) => [l.id, l.etat]));
  const ids = [...new Set([...soumise.lignes.map((l) => l.id), ...validee.lignes.map((l) => l.id)])];
  const parId = (v) => Object.fromEntries(v.lignes.map((l) => [l.id, l]));
  const a = parId(soumise);
  const b = parId(validee);
  return (
    <Table caption="Version soumise et version validée côte à côte" minWidth="720px" cards>
      <thead><tr><Th>Date</Th><Th>Session / type</Th><Th>{titreSoumise}</Th><Th>{titreValidee}</Th></tr></thead>
      <tbody>
        {ids.map((id) => {
          const ref = b[id] || a[id];
          return (
            <Tr key={id} fond={ETATS[etat[id] || 'identique'].fond}>
              <Td label="Date">{formatDateCourte(ref.date)}</Td>
              <Td label="Session / type">{objet(ref)}{etat[id] && etat[id] !== 'identique' ? ` — ${ETATS[etat[id]].label.toLowerCase()}` : ''}</Td>
              <Td label={titreSoumise}>{valeur(a[id])}</Td>
              <Td label={titreValidee}>{valeur(b[id])}</Td>
            </Tr>
          );
        })}
      </tbody>
      <tfoot><tr><Td label="Total" colSpan={2}><strong>Total</strong></Td><Td label={titreSoumise}><strong>{formatHeures(soumise.total_heures)} · {formatEuros(soumise.total_eur)}</strong></Td><Td label={titreValidee}><strong>{formatHeures(validee.total_heures)} · {formatEuros(validee.total_eur)}</strong></Td></tr></tfoot>
    </Table>
  );
}

/**
 * TS-04 (écran directeur) : la version « soumise » (le mois tel qu'envoyé) et la version « validée » (le mois tel
 * que validé), en entier ou comparées. Seule la validée est signée et générée en PDF. Le serveur calcule l'écart.
 *
 * @param {object} props
 * @param {number} props.professeurId
 * @param {number} props.annee
 * @param {number} props.mois
 * @param {*} props.rafraichir change à chaque rechargement du détail du mois (relance la lecture des versions)
 */
export default function VersionsMois({ professeurId, annee, mois, rafraichir }) {
  const mobile = useMediaQuery('(max-width: 860px)');
  const [vue, setVue] = useState(null); // null = vue par défaut selon la disponibilité
  const [soumiseNumero, setSoumiseNumero] = useState(undefined);
  const [valideeNumero, setValideeNumero] = useState(undefined);
  const versions = useVersionsMois(professeurId, annee, mois, { soumiseNumero, valideeNumero, rafraichir });
  const d = versions.data;

  if (versions.loading && !d) return <LoadingBlock message="Chargement des versions du mois…" lignes={3} />;
  if (versions.error) return <ErrorBlock message="Impossible de charger les versions de ce mois. Réessayez." onRetry={versions.reload} />;
  if (!d) return null;

  const { soumise, validee, ecart } = d;
  if (!soumise && !validee) {
    return (
      <Banner tone="warning">
        <strong>Non disponible avec TS-04.</strong> Ce mois a été soumis avant la mise en place des versions : la soumission d'origine n'a pas été conservée.
      </Banner>
    );
  }

  const comparable = Boolean(soumise && validee && ecart);
  const defaut = comparable ? 'ecarts' : validee ? 'validee' : 'soumise';
  const dispo = { ecarts: comparable, soumise: Boolean(soumise), validee: Boolean(validee), cote: comparable };
  const courante = vue && dispo[vue] ? vue : defaut;
  const segments = [['ecarts', 'Écarts'], ['soumise', 'Soumise'], ['validee', 'Validée'], ...(mobile ? [] : [['cote', 'Côte à côte']])];
  const ajustees = ecart ? ecart.lignes.filter((l) => l.etat !== 'identique').length : 0;
  const hs = d.historique.soumises;
  const hv = d.historique.validees;
  const raisonIndispo = !soumise
    ? 'Non disponible avec TS-04 : la soumission d\'origine n\'a pas été conservée.'
    : 'Disponible une fois le mois validé.';

  return (
    <div style={{ marginBottom: ADMIN_SPACING.lg }}>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: ADMIN_SPACING.sm, alignItems: 'center', marginBottom: ADMIN_SPACING.sm }}>
        {soumise ? <StatutBadge label={`Soumise v${soumise.numero}${soumise.origine === 'direction' ? ` — par ${soumise.auteur || 'la direction'} (direction)` : ''} · ${formatDateHeure(soumise.created_at)}`} tone="neutral" /> : <StatutBadge label="Soumise : non disponible avec TS-04" tone="warning" />}
        <span aria-hidden="true">→</span>
        {validee ? <StatutBadge label={`Validée v${validee.numero} · ${formatDateHeure(validee.created_at)}`} tone="success" /> : <StatutBadge label="Validée : en attente" tone="neutral" />}
        {comparable && (ecart.identique ? <StatutBadge label="Identique à la soumission" tone="info" /> : <StatutBadge label={`${ajustees} ligne${ajustees > 1 ? 's' : ''} ajustée${ajustees > 1 ? 's' : ''}`} tone="warning" />)}
        {hs.length > 1 && (
          <label style={{ fontSize: 13 }}>Soumise :{' '}
            <select aria-label="Version soumise" value={soumise?.numero} onChange={(e) => setSoumiseNumero(Number(e.target.value))}>
              {hs.map((v) => <option key={v.numero} value={v.numero}>v{v.numero}{v.statut === 'active' ? ' (active)' : ' (remplacée)'}</option>)}
            </select>
          </label>
        )}
        {hv.length > 1 && (
          <label style={{ fontSize: 13 }}>Validée :{' '}
            <select aria-label="Version validée" value={validee?.numero} onChange={(e) => setValideeNumero(Number(e.target.value))}>
              {hv.map((v) => <option key={v.numero} value={v.numero}>v{v.numero}{v.statut === 'active' ? ' (active)' : ' (remplacée)'}</option>)}
            </select>
          </label>
        )}
      </div>

      <Banner tone="info">
        <strong>Seule la version validée est signée et générée en PDF.</strong>{' '}
        La version soumise est conservée comme référence ; elle ne peut être ni modifiée ni signée.
      </Banner>
      {validee && validee.statut === 'remplacee' && (
        <Banner tone="warning">Cette version validée est remplacée par une version plus récente ; elle reste consultable en lecture seule.</Banner>
      )}

      <div role="group" aria-label="Affichage des versions" style={{ display: 'inline-flex', flexWrap: 'wrap', gap: 6, marginBottom: ADMIN_SPACING.md }}>
        {segments.map(([cle, libelle]) => (
          <AdminButton
            key={cle}
            size="sm"
            variant={courante === cle ? 'primary' : 'secondary'}
            aria-pressed={courante === cle}
            aria-disabled={!dispo[cle]}
            disabled={!dispo[cle]}
            title={dispo[cle] ? undefined : raisonIndispo}
            onClick={() => setVue(cle)}
          >
            {libelle}
          </AdminButton>
        ))}
      </div>
      <p aria-live="polite" className="sr-only">Vue {segments.find(([c]) => c === courante)?.[1]} affichée</p>

      {courante === 'ecarts' && comparable && (
        <Vue label="Écarts">
          {ecart.identique ? (
            <Banner tone="info" actions={<AdminButton size="sm" variant="secondary" onClick={() => setVue('validee')}>Voir la version validée</AdminButton>}>
              <strong>Identique à la soumission.</strong> Aucun écart : le mois a été validé tel que soumis ({formatHeures(validee.total_heures)} · {formatEuros(validee.total_eur)}).
            </Banner>
          ) : <TableEcarts ecart={ecart} />}
        </Vue>
      )}
      {courante === 'soumise' && soumise && <Vue label="Version soumise"><TableVersion version={soumise} titre="Version soumise" /></Vue>}
      {courante === 'validee' && validee && <Vue label="Version validée"><TableVersion version={validee} titre="Version validée" /></Vue>}
      {courante === 'cote' && comparable && <Vue label="Côte à côte"><CoteACote soumise={soumise} validee={validee} ecart={ecart} /></Vue>}
      {soumise?.empreinte && courante === 'soumise' && !soumise.detail_efface && (
        <p style={{ fontSize: 12, color: ADMIN_COLORS.textSecondary }}>Empreinte : {soumise.empreinte.slice(0, 8)}… · soumise par {soumise.auteur || 'le professeur'}.</p>
      )}
      {validee?.empreinte && courante === 'validee' && !validee.detail_efface && (
        <p style={{ fontSize: 12, color: ADMIN_COLORS.textSecondary }}>Empreinte : {validee.empreinte.slice(0, 8)}… · validée par {validee.auteur || 'la direction'}.</p>
      )}
    </div>
  );
}
